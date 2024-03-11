<?php

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Onlime\LaravelSqlReporter\Listeners\LogSqlQuery;
use Onlime\LaravelSqlReporter\SqlLogger;

beforeEach(function () {
    $this->logger = Mockery::spy(SqlLogger::class);
});

it('can handle the command finished event', function () {
    $listener = new LogSqlQuery($this->logger);
    $listener->handle(Mockery::mock(CommandFinished::class));

    $this->logger->shouldHaveReceived('log')->once();
});

it('can handle the request handled event', function () {
    $listener = new LogSqlQuery($this->logger);
    $listener->handle(Mockery::mock(RequestHandled::class));

    $this->logger->shouldHaveReceived('log')->once();
});
