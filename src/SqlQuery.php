<?php

namespace Onlime\LaravelSqlReporter;

class SqlQuery
{
    public function __construct(
        private int $number,
        private string $rawQuery,
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
     * Get raw SQL query with embedded bindings.
     */
    public function rawQuery(): string
    {
        return $this->rawQuery;
    }

    /**
     * Get query execution time.
     */
    public function time(): float
    {
        return $this->time;
    }
}
