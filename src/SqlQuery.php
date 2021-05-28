<?php

namespace Onlime\LaravelSqlReporter;

use Onlime\LaravelSqlReporter\Concerns\ReplacesBindings;

class SqlQuery
{
    use ReplacesBindings;

    /**
     * SqlQuery constructor.
     *
     * @param int $number
     * @param string $sql
     * @param array $bindings
     * @param float $time
     */
    public function __construct(
        private int $number,
        private string $sql,
        private array $bindings,
        private float $time
    ) {}

    /**
     * Get query number.
     *
     * @return int
     */
    public function number()
    {
        return $this->number;
    }

    /**
     * Get raw SQL (without bindings).
     *
     * @return string
     */
    public function raw()
    {
        return $this->sql;
    }

    /**
     * Get bindings.
     *
     * @return array
     */
    public function bindings()
    {
        return $this->bindings;
    }

    /**
     * Get query execution time.
     *
     * @return float
     */
    public function time()
    {
        return $this->time;
    }

    /**
     * Get full query with values from bindings inserted.
     *
     * @return string
     */
    public function get()
    {
        return $this->replaceBindings($this->sql, $this->bindings);
    }
}
