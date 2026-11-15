<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Mockery\Matcher\Closure;
use Onlime\LaravelSqlReporter\SqlLogger;
use Onlime\LaravelSqlReporter\SqlQuery;
use Onlime\LaravelSqlReporter\Support\Redactor;
use Onlime\LaravelSqlReporter\Writer;

beforeEach(function () {
    $this->writer = Mockery::mock(Writer::class);
    $this->logger = new SqlLogger($this->writer, app(Redactor::class));
    DB::flushQueryLog();
});

/**
 * Match a query passed to the writer (ignoring its execution time).
 *
 * @param  array<mixed>  $bindings
 */
function loggedQuery(int $number, string $rawQuery, string $query, array $bindings): Closure
{
    return Mockery::on(fn (SqlQuery $actual) => [$actual->number, $actual->rawQuery, $actual->query, $actual->bindings]
        === [$number, $rawQuery, $query, $bindings]);
}

it('runs writer with valid query', function () {
    DB::select('select ? as name', ['Alice']);

    $this->writer->shouldReceive('writeQuery')->once()->with(loggedQuery(1, "select 'Alice' as name", 'select ? as name', ['Alice']));
    // the log entry gets written before it's reported
    $this->writer->shouldReceive('flush')->once()->withNoArgs()->ordered();
    $this->writer->shouldReceive('report')->once()->withNoArgs()->ordered();

    $this->logger->log();
});

it('logs queries without execution time', function () {
    DB::logQuery('select ? as name', ['Alice']);

    $this->writer->shouldReceive('writeQuery')->once()->with(Mockery::on(fn (SqlQuery $query) => $query->time === 0.0));
    $this->writer->shouldReceive('flush')->once()->withNoArgs();
    $this->writer->shouldReceive('report')->once()->withNoArgs();

    $this->logger->log();
});

it('uses valid query number for multiple queries', function () {
    DB::select('select ? as name', ['Alice']);
    DB::select('select ? as id', [2]);

    $this->writer->shouldReceive('writeQuery')->once()->with(loggedQuery(1, "select 'Alice' as name", 'select ? as name', ['Alice']));
    $this->writer->shouldReceive('writeQuery')->once()->with(loggedQuery(2, 'select 2 as id', 'select ? as id', [2]));
    $this->writer->shouldReceive('flush')->once()->withNoArgs();
    $this->writer->shouldReceive('report')->once()->withNoArgs();

    $this->logger->log();
});
