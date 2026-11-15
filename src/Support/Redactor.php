<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter\Support;

use Illuminate\Support\Str;
use Onlime\LaravelSqlReporter\Config;
use Onlime\LaravelSqlReporter\Writer;

/**
 * Redacts the secrets among the values (bindings) of a logged query: password hashes, of which only the algorithm
 * identifier is kept (e.g. $2y$[redacted]), the values of the columns in the redact_columns option, and the values
 * the Writer::redactBindingUsing() callback asks for.
 */
class Redactor
{
    /**
     * Replacement for redacted values.
     */
    public const string REDACTED = '[redacted]';

    /**
     * A password hash in the modular crypt format, $<identifier>$…, e.g. $2y$ (bcrypt), $argon2id$, or legacy ones like
     * $1$ (MD5-crypt), $6$ (SHA-512-crypt) or $P$ (phpass), with at least 16 more characters and no whitespace, unlike
     * short or spaced values like $5$ or $x$ + $y$. Its identifier is the first group.
     */
    private const string HASH = '/^\$([\w-]+)\$\S{16,}$/';

    public function __construct(
        private readonly Config $config
    ) {}

    /**
     * Get the redacted values of a query, by the keys of its bindings. Null values are never redacted.
     *
     * @param  array<mixed>  $bindings
     * @return array<array-key, string>
     */
    public function redact(string $query, array $bindings, string $tablePrefix = ''): array
    {
        $patterns = array_map(Str::lower(...), $this->config->redactColumns());
        $columns = null;
        $redacted = [];
        $position = 0;

        foreach ($bindings as $key => $value) {
            $index = $position++;

            if ($value === null) {
                continue;
            }

            if (is_string($value) && ($hash = $this->redactHash($value)) !== null) {
                $redacted[$key] = $hash;

                continue;
            }

            [$table, $column] = ($columns ??= QueryColumns::resolve($query, $tablePrefix))[$index] ?? [null, null];

            if (($column !== null && $this->matches($patterns, $table, $column))
                || Writer::shouldRedactBinding($value, $column, $table, $query)) {
                $redacted[$key] = self::REDACTED;
            }
        }

        return $redacted;
    }

    /**
     * Get a password hash redacted except for its algorithm identifier, e.g. $2y$[redacted], or null if the value
     * is no password hash.
     */
    private function redactHash(string $value): ?string
    {
        $algorithm = Str::match(self::HASH, $value);

        return $algorithm !== '' ? '$'.$algorithm.'$'.self::REDACTED : null;
    }

    /**
     * Whether a column matches any of the patterns, which are column names or table.column, lowercase.
     *
     * @param  array<int, string>  $patterns
     */
    private function matches(array $patterns, ?string $table, string $column): bool
    {
        $table = $table !== null ? Str::lower($table) : null;
        $column = Str::lower($column);

        foreach ($patterns as $pattern) {
            if (! str_contains($pattern, '.')) {
                if (Str::is($pattern, $column)) {
                    return true;
                }
            } elseif ($table !== null) {
                [$tablePattern, $columnPattern] = explode('.', $pattern, 2);
                if (Str::is($tablePattern, $table) && Str::is($columnPattern, $column)) {
                    return true;
                }
            }
        }

        return false;
    }
}
