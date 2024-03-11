<?php

namespace Tests\Unit;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Mockery;
use Onlime\LaravelSqlReporter\Listeners\LogSqlQuery;
use Onlime\LaravelSqlReporter\SqlLogger;

class LogSqlQueryTest extends UnitTestCase
{
    /**
     * @var SqlLogger
     */
    private $logger;

    protected function setUp(): void
    {
        $this->logger = Mockery::spy(SqlLogger::class);
    }

    /** @test */
    public function it_can_handle_the_command_finished_event()
    {
        $listener = new LogSqlQuery($this->logger);
        $listener->handle(Mockery::mock(CommandFinished::class));

        $this->logger->shouldHaveReceived('log')->once();
    }

    /** @test */
    public function it_can_handle_the_request_handled_event()
    {
        $listener = new LogSqlQuery($this->logger);
        $listener->handle(Mockery::mock(RequestHandled::class));

        $this->logger->shouldHaveReceived('log')->once();
    }
}
