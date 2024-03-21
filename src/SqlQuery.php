<?php

namespace Onlime\LaravelSqlReporter;

readonly class SqlQuery
{
    public function __construct(
        public int $number,
        public string $rawQuery,
        public float $time,
        public string $query,
        public array $bindings = []
    ) {
    }
}
