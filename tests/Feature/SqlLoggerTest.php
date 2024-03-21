<?php

use Illuminate\Support\Facades\DB;
use Onlime\LaravelSqlReporter\SqlLogger;
use Onlime\LaravelSqlReporter\SqlQuery;
use Onlime\LaravelSqlReporter\Writer;

beforeEach(function () {
    $this->writer = Mockery::mock(Writer::class);
    $this->logger = new SqlLogger($this->writer);
});

it('runs writer with valid query', function () {
    DB::shouldReceive('getQueryLog')->once()->withNoArgs()->andReturn([
        ['query' => 'anything', 'bindings' => []],
    ]);

    DB::shouldReceive('getRawQueryLog')->once()->withNoArgs()->andReturn([
        ['raw_query' => 'anything', 'time' => 1.23],
    ]);

    $sqlQuery = SqlQuery::make(1, 'anything', 1.23);
    $this->writer->shouldReceive('writeQuery')->once()->with(Mockery::on(fn ($arg) => $sqlQuery == $arg));
    $this->writer->shouldReceive('report')->once()->withNoArgs();

    $this->logger->log();
    expect(true)->toBeTrue();
});

it('uses valid query number for multiple queries', function () {
    DB::shouldReceive('getQueryLog')->once()->withNoArgs()->andReturn([
        ['query' => 'anything', 'bindings' => []],
        ['query' => 'anything2', 'bindings' => []],
    ]);

    DB::shouldReceive('getRawQueryLog')->once()->withNoArgs()->andReturn([
        ['raw_query' => 'anything', 'time' => 1.23],
        ['raw_query' => 'anything2', 'time' => 4.56],
    ]);

    $sqlQuery = SqlQuery::make(1, 'anything', 1.23);
    $this->writer->shouldReceive('writeQuery')->once()->with(Mockery::on(fn ($arg) => $sqlQuery == $arg));

    $sqlQuery2 = SqlQuery::make(2, 'anything2', 4.56);
    $this->writer->shouldReceive('writeQuery')->once()->with(Mockery::on(fn ($arg) => $sqlQuery2 == $arg));

    $this->writer->shouldReceive('report')->once()->withNoArgs();

    $this->logger->log();
});
