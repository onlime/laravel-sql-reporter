<?php

declare(strict_types=1);

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Onlime\LaravelSqlReporter\Listeners\LogSqlQuery;
use Onlime\LaravelSqlReporter\SqlLogger;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

beforeEach(function () {
    $this->logger = Mockery::spy(SqlLogger::class);
    $this->app->instance(SqlLogger::class, $this->logger);
    $this->listener = app(LogSqlQuery::class);
});

function commandStarting(string $command = 'sql-reporter:test'): CommandStarting
{
    return new CommandStarting($command, new ArrayInput([]), new NullOutput);
}

function commandFinished(string $command = 'sql-reporter:test'): CommandFinished
{
    return new CommandFinished($command, new ArrayInput([]), new NullOutput, 0);
}

it('logs right away when a command has finished', function () {
    // Laravel invokes deferred callbacks in its own CommandFinished listener, which runs before this one,
    // so a deferred callback would never run
    $this->listener->handle(commandStarting());
    $this->logger->shouldNotHaveReceived('log');

    $this->listener->handle(commandFinished());

    $this->logger->shouldHaveReceived('log')->once();
    expect(app(DeferredCallbackCollection::class))->toHaveCount(0);
});

it('defers the logging until the response of a request has been sent', function () {
    App::shouldReceive('runningInConsole')->andReturn(false);

    $this->listener->handle(Mockery::mock(RequestHandled::class));

    $this->logger->shouldNotHaveReceived('log');

    // also invoked for failed requests (status code >= 400)
    app(DeferredCallbackCollection::class)->invokeWhen(fn ($callback) => $callback->always);

    $this->logger->shouldHaveReceived('log')->once();
});

it('ignores the event of the other context to prevent duplicate logging', function (bool $runningInConsole, Closure $event) {
    App::shouldReceive('runningInConsole')->andReturn($runningInConsole);

    $this->listener->handle($event());

    $this->logger->shouldNotHaveReceived('log');
})->with([
    'request handled while running in console' => [true, fn () => Mockery::mock(RequestHandled::class)],
    'command starting while serving a request (e.g. Artisan::call())' => [false, fn () => commandStarting()],
    'command finished while serving a request (e.g. Artisan::call())' => [false, fn () => commandFinished()],
]);

it('only logs the outermost command, including the commands it calls', function () {
    $this->listener->handle(commandStarting('outer'));
    $this->listener->handle(commandStarting('inner'));
    $this->listener->handle(commandFinished('inner'));

    $this->logger->shouldNotHaveReceived('log');

    $this->listener->handle(commandFinished('outer'));

    $this->logger->shouldHaveReceived('log')->once();
});

it('does not log the command of a queue worker, which logs each job separately', function () {
    // creates the Artisan application, which is needed to look up the command class
    Artisan::all();

    $this->listener->handle(commandStarting('queue:work'));
    $this->listener->handle(commandFinished('queue:work'));

    $this->logger->shouldNotHaveReceived('log');
});
