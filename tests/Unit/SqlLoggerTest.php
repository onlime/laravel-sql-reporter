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
        DB::shouldReceive('getRawQueryLog')->once()->withNoArgs()->andReturn([
            ['raw_query' => 'anything', 'time' => 1.23],
        ]);

        $sqlQuery = new SqlQuery(1, 'anything', 1.23);
        $this->writer->shouldReceive('writeQuery')->once()->with(Mockery::on(fn ($arg) => $sqlQuery == $arg));

        $this->logger->log();
        $this->assertTrue(true);
    }

    /** @test */
    public function it_uses_valid_query_number_for_multiple_queries()
    {
        DB::shouldReceive('getRawQueryLog')->once()->withNoArgs()->andReturn([
            ['raw_query' => 'anything', 'time' => 1.23],
            ['raw_query' => 'anything2', 'time' => 4.56],
        ]);

        $sqlQuery = new SqlQuery(1, 'anything', 1.23);
        $this->writer->shouldReceive('writeQuery')->once()->with(Mockery::on(fn ($arg) => $sqlQuery == $arg));

        $sqlQuery2 = new SqlQuery(2, 'anything2', 4.56);
        $this->writer->shouldReceive('writeQuery')->once()->with(Mockery::on(fn ($arg) => $sqlQuery2 == $arg));

        $this->logger->log();
        $this->assertTrue(true);
    }
}
