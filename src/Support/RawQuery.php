<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Builds the raw SQL of a logged query with its bindings embedded, like Connection::getRawQueryLog(). Unlike it, a
 * value that can't be escaped as a string (e.g. binary data, or any value with a null byte or invalid UTF-8) doesn't
 * make it fail, which would otherwise prevent the whole request, command or job from being logged. Secrets among the
 * values are redacted, if a Redactor is given.
 */
class RawQuery
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ?Redactor $redactor = null,
    ) {}

    /**
     * Get the raw SQL of a query with its bindings embedded.
     *
     * @param  array<mixed>  $bindings
     */
    public function build(string $query, array $bindings): string
    {
        $bindings = $this->connection->prepareBindings($bindings);
        $grammar = $this->connection->getQueryGrammar();

        // A redacted value is a plain string (e.g. [redacted]), which the grammar escapes like any other
        if ($this->redactor !== null) {
            $bindings = array_replace(
                $bindings,
                $this->redactor->redact($query, $bindings, $this->connection->getTablePrefix())
            );
        }

        try {
            return $grammar->substituteBindingsIntoRawSql($query, $bindings);
        } catch (Throwable) {
            // at least one of the values can't be escaped as a string
        }

        // We can't pass the grammar a ready-made literal (e.g. x'00ff') for such a value, as the grammar escapes every
        // binding itself and would quote the literal as a string. And we don't want to embed the bindings ourselves,
        // as the grammar knows where they go (e.g. not within string literals, or PostgreSQL's ?| operators).
        // So we pass the grammar a unique placeholder string instead of each such value, which it embeds quoted like
        // any other string, and afterwards replace each quoted placeholder by the literal of its value.
        $literals = [];
        foreach ($bindings as $key => $value) {
            try {
                $this->connection->escape($value);
            } catch (Throwable) {
                $placeholder = 'sql-reporter-binding-'.Str::random(32);
                $literals[$this->connection->escape($placeholder)] = $this->literal($value);
                $bindings[$key] = $placeholder;
            }
        }

        return strtr($grammar->substituteBindingsIntoRawSql($query, $bindings), $literals);
    }

    /**
     * Get the SQL literal of a value that can't be escaped as a string.
     */
    private function literal(mixed $value): string
    {
        if (is_string($value)) {
            try {
                // a hex literal, e.g. x'00ff' for MySQL and SQLite
                return $this->connection->escape($value, true);
            } catch (Throwable) {
                // the database driver doesn't support binary literals
                return $this->connection->escape(sprintf('<binary %d bytes>', strlen($value)));
            }
        }

        // e.g. a stream resource
        return $this->connection->escape('<'.get_debug_type($value).'>');
    }
}
