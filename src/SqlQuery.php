<?php

namespace Onlime\LaravelSqlReporter;

use Onlime\LaravelSqlReporter\Concerns\ReplacesBindings;

class SqlQuery
{
    use ReplacesBindings;

    public function __construct(
        private int $number,
        private string $sql,
        private ?array $bindings,
        private float $time
    ) {
    }

    /**
     * Get query number.
     */
    public function number(): int
    {
        return $this->number;
    }

    /**
     * Get raw SQL (without bindings).
     */
    public function raw(): string
    {
        return $this->sql;
    }

    /**
     * Get bindings.
     */
    public function bindings(): array
    {
        return $this->bindings ?? [];
    }

    /**
     * Get query execution time.
     */
    public function time(): float
    {
        return $this->time;
    }

    /**
     * Get full query with values from bindings inserted.
     */
    public function get(): string
    {
        return $this->replaceBindings($this->sql, $this->bindings());
    }
}
