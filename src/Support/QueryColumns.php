<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter\Support;

use Illuminate\Support\Str;

/**
 * Determines the table and column of each binding (? placeholder) of a query, as far as possible, from the text
 * before it, e.g. "email" for `where "email" = ?` or `set "email" = ?`, or from its position among the values of an
 * insert. It's meant for redacting values, not a full SQL parser: the column of a binding in an expression, e.g.
 * `lower("email") = ?`, or among the values of an insert with expressions, e.g. now(), is unknown.
 */
final class QueryColumns
{
    /**
     * A binding. Like Grammar::substituteBindingsIntoRawSql(), it skips a ? within a string literal or an escaped ??.
     */
    private const string BINDING = <<<'REGEX'
        /'(?:''|\\'|[^'])*+' (*SKIP)(*FAIL)   # a string literal, e.g. 'it''s ?'
        | \?\? (*SKIP)(*FAIL)                 # an escaped ??, which is PostgreSQL's ? operator
        | \?                                  # a binding
        /x
        REGEX;

    /**
     * The table of a query: the one it inserts into, updates or deletes from, or else the first one it selects from.
     * Identifiers may be quoted for MySQL (`users`), PostgreSQL / SQLite ("users") or SQL Server ([users]).
     */
    private const string TABLE = <<<'REGEX'
        /^\s*(?:
            (?:insert|replace)\b.*?\binto     # insert into, insert ignore into, ...
            | update
            | (?:delete|select)\b.*?\bfrom
        )\s+
        (?:[`"\[]?\w+[`"\]]?\.)?              # the schema, if any, e.g. "public".
        [`"\[]?(\w+)                          # the table, e.g. "users"
        /isx
        REGEX;

    /**
     * A column compared to the binding right after it, e.g. `"users"."email" = ?` or `"id" in (?`.
     */
    private const string COMPARISON = <<<'REGEX'
        /(?:[`"\[]?(\w+)[`"\]]?\.)?           # the table, if the column is qualified, e.g. "users".
        [`"\[]?(\w+)[`"\]]?\s*                # the column, e.g. "email"
        (?:
            [=<>!~]+\*?                       # =, !=, <>, <=, >=, <=>, ~, ~*, !~, ...
            | (?:not\s+)?(?:i?like|in|between|regexp|rlike|similar\s+to)
            | is(?:\s+not)?
        )
        \s*\(?\s*$                            # right before the binding, or the ( of an in list
        /ix
        REGEX;

    /**
     * An insert: its columns, and its rows of values.
     */
    private const string INSERT = <<<'REGEX'
        /^\s*(?:insert|replace)\b[^(]*        # insert into "users", insert ignore into "users", ...
        \(([^()]*)\)\s*                       # the columns, e.g. ("name", "email")
        values(.*?)                           # the rows of values, e.g. (?, ?), (?, ?)
        (?:\b(?:on\s+duplicate\s+key|on\s+conflict|returning)\b|$)   # up to an upsert's update clause or returning
        /isx
        REGEX;

    /**
     * Get the table and column of each binding, in the order of the bindings. The table is the one the column is
     * qualified with, or the table of the query, without the table prefix. Either may be null if unknown.
     *
     * @return list<array{0: string|null, 1: string|null}>
     */
    public static function resolve(string $sql, string $tablePrefix = ''): array
    {
        $table = Str::chopStart(Str::match(self::TABLE, $sql), $tablePrefix) ?: null;
        $insertColumns = self::insertColumns($sql);
        $columns = [];
        $previous = [$table, null];

        foreach (self::textBeforeBindings($sql) as $index => $before) {
            $columns[] = $previous = match (true) {
                isset($insertColumns[$index]) => [$table, $insertColumns[$index]],
                // the next value of an `in (?, ?)` list or a `between ? and ?` range
                preg_match('/^\s*(?:,|and)\s*$/i', $before) === 1 => $previous,
                preg_match(self::COMPARISON, $before, $matches) === 1 => [
                    $matches[1] !== '' ? Str::chopStart($matches[1], $tablePrefix) : $table,
                    $matches[2],
                ],
                default => [$table, null],
            };
        }

        return $columns;
    }

    /**
     * Get the text before each binding, since the previous one.
     *
     * @return list<string>
     */
    private static function textBeforeBindings(string $sql): array
    {
        return array_slice(preg_split(self::BINDING, $sql) ?: [], 0, -1);
    }

    /**
     * Get the columns of the bindings among the values of an insert, which come first, e.g. "name", "email", "name",
     * "email" for `insert into "users" ("name", "email") values (?, ?), (?, ?)`. Empty if it's no insert, or if
     * expressions among the values (e.g. now()) don't let the bindings be matched to the columns.
     *
     * @return array<int, string>
     */
    private static function insertColumns(string $sql): array
    {
        if (preg_match(self::INSERT, $sql, $matches) !== 1) {
            return [];
        }

        $columns = Str::of($matches[1])->explode(',')->map(fn (string $column) => trim($column, ' `"[]'))->all();
        $rows = substr_count(preg_replace('/\s+/', '', $matches[2]) ?? '', '),(') + 1;

        // Each row has one binding per column, unless some values are expressions
        return count(self::textBeforeBindings($matches[2])) === $rows * count($columns)
            ? array_merge(...array_fill(0, $rows, $columns))
            : [];
    }
}
