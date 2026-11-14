<?php

declare(strict_types=1);

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\App;
use Onlime\LaravelSqlReporter\Listeners\LogSqlQuery;
use Onlime\LaravelSqlReporter\SqlLogger;

beforeEach(function () {
    $this->withoutDefer();
    $this->logger = Mockery::spy(SqlLogger::class);
});

it('can handle the command finished event', function () {
    $listener = new LogSqlQuery($this->logger);
    $listener->handle(Mockery::mock(CommandFinished::class));

    $this->logger->shouldHaveReceived('log')->once();
});

it('can handle the request handled event', function () {
    App::shouldReceive('runningInConsole')->andReturn(false);

    $listener = new LogSqlQuery($this->logger);
    $listener->handle(Mockery::mock(RequestHandled::class));

    $this->logger->shouldHaveReceived('log')->once();
});

it('ignores the event of the other context to prevent duplicate logging', function (bool $runningInConsole, string $event) {
    App::shouldReceive('runningInConsole')->andReturn($runningInConsole);

    $listener = new LogSqlQuery($this->logger);
    $listener->handle(Mockery::mock($event));

    $this->logger->shouldNotHaveReceived('log');
})->with([
    'request handled while running in console' => [true, RequestHandled::class],
    'command finished while serving a request (e.g. Artisan::call())' => [false, CommandFinished::class],
]);
