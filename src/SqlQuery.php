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
    ) {}

    public static function make(
        int $number,
        string $rawQuery,
        float $time,
        ?string $query = null,
        array $bindings = []
    ): self {
        return new self($number, $rawQuery, $time, $query ?? $rawQuery, $bindings);
    }
}
