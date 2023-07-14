<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Mockery;
use Onlime\LaravelSqlReporter\SqlLogger;
use Onlime\LaravelSqlReporter\SqlQuery;
use Onlime\LaravelSqlReporter\Writer;

class SqlLoggerTest extends UnitTestCase
{
    /**
     * @var Writer|\Mockery\Mock
     */
    private $writer;

    /**
     * @var SqlLogger
     */
    private $logger;

    protected function setUp(): void
    {
        $this->writer = Mockery::mock(Writer::class);
        $this->logger = new SqlLogger($this->writer);
    }

    /** @test */
    public function it_runs_writer_with_valid_query()
    {
        DB::shouldReceive('getQueryLog')->once()->withNoArgs()->andReturn([
            ['query' => 'anything', 'bindings' => [], 'time' => 1.23],
        ]);

        $sqlQuery = new SqlQuery(1, 'anything', [], 1.23);
        //        $this->writer->shouldReceive('writeQuery')->once()->with($sqlQuery)
        $this->writer->shouldReceive('writeQuery')->once()->with(Mockery::on(function ($arg) use ($sqlQuery) {
            return $sqlQuery == $arg;
        }));

        $this->logger->log();
        $this->assertTrue(true);
    }

    /** @test */
    public function it_uses_valid_query_number_for_multiple_queries()
    {
        DB::shouldReceive('getQueryLog')->once()->withNoArgs()->andReturn([
            ['query' => 'anything', 'bindings' => ['one', 1], 'time' => 1.23],
            ['query' => 'anything2', 'bindings' => ['two', 2], 'time' => 4.56],
        ]);

        $sqlQuery = new SqlQuery(1, 'anything', ['one', 1], 1.23);
        //        $this->writer->shouldReceive('writeQuery')->once()->with($sqlQuery);
        $this->writer->shouldReceive('writeQuery')->once()->with(Mockery::on(function ($arg) use ($sqlQuery) {
            return $sqlQuery == $arg;
        }));

        $sqlQuery2 = new SqlQuery(2, 'anything2', ['two', 2], 4.56);
        //        $this->writer->shouldReceive('writeQuery')->once()->with($sqlQuery2);
        $this->writer->shouldReceive('writeQuery')->once()->with(Mockery::on(function ($arg) use ($sqlQuery2) {
            return $sqlQuery2 == $arg;
        }));

        $this->logger->log();
        $this->assertTrue(true);
    }
}
