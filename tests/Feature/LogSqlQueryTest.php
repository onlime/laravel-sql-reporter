<?php

declare(strict_types=1);

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\App;
use Onlime\LaravelSqlReporter\Listeners\LogSqlQuery;
use Onlime\LaravelSqlReporter\SqlLogger;
use Onlime\LaravelSqlReporter\Support\AttributeResolver;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

beforeEach(function () {
    $this->logger = Mockery::spy(SqlLogger::class);
});

it('logs right away when a command has finished', function () {
    // Laravel invokes deferred callbacks in its own CommandFinished listener, which runs before this one,
    // so a deferred callback would never run
    $listener = new LogSqlQuery($this->logger, new AttributeResolver);
    $listener->handle(new CommandFinished('sql-reporter:test', new ArrayInput([]), new NullOutput, 0));

    $this->logger->shouldHaveReceived('log')->once();
    expect(app(DeferredCallbackCollection::class))->toHaveCount(0);
});

it('defers the logging until the response of a request has been sent', function () {
    App::shouldReceive('runningInConsole')->andReturn(false);

    $listener = new LogSqlQuery($this->logger, new AttributeResolver);
    $listener->handle(Mockery::mock(RequestHandled::class));

    $this->logger->shouldNotHaveReceived('log');

    // also invoked for failed requests (status code >= 400)
    app(DeferredCallbackCollection::class)->invokeWhen(fn ($callback) => $callback->always);

    $this->logger->shouldHaveReceived('log')->once();
});

it('ignores the event of the other context to prevent duplicate logging', function (bool $runningInConsole, string $event) {
    App::shouldReceive('runningInConsole')->andReturn($runningInConsole);

    $listener = new LogSqlQuery($this->logger, new AttributeResolver);
    $listener->handle(Mockery::mock($event));

    $this->logger->shouldNotHaveReceived('log');
})->with([
    'request handled while running in console' => [true, RequestHandled::class],
    'command finished while serving a request (e.g. Artisan::call())' => [false, CommandFinished::class],
]);
