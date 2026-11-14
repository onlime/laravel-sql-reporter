<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter\Listeners;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\App;
use Onlime\LaravelSqlReporter\Attributes\DontLogQueries;
use Onlime\LaravelSqlReporter\Attributes\DontReportQueries;
use Onlime\LaravelSqlReporter\SqlLogger;
use Onlime\LaravelSqlReporter\Support\AttributeResolver;
use Onlime\LaravelSqlReporter\Support\ConsoleState;

readonly class LogSqlQuery
{
    /**
     * Create the event listener.
     */
    public function __construct(
        private SqlLogger $logger,
        private AttributeResolver $attributes,
        private ConsoleState $state,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(CommandStarting|CommandFinished|RequestHandled $event): void
    {
        // Prevent duplicate logging when running in request (RequestHandled event)
        // and programmatically executing Artisan commands (CommandStarting / CommandFinished events).
        if ($event instanceof RequestHandled === App::runningInConsole()) {
            return;
        }

        if ($event instanceof RequestHandled) {
            // Push the logging to the background, after the response has been sent.
            defer(fn () => $this->logger->log())->always();

            return;
        }

        // Only the outermost command is logged. Commands it calls with Artisan::call() are part of its log.
        $outermost = $event instanceof CommandStarting
            ? $this->state->commandStarting()
            : $this->state->commandFinished();
        if (! $outermost || $event instanceof CommandStarting) {
            return;
        }

        $classes = $this->attributes->commandClasses($event->command);
        if ($this->attributes->has($classes, DontLogQueries::class)) {
            return;
        }

        // A queue worker logs the queries of each job separately, but not its own (e.g. polling a database queue)
        if ($this->state->isQueueWorker()) {
            return;
        }

        // Log right away, as there is no response to be sent first. A deferred callback would never run, because
        // Laravel invokes deferred callbacks in its own CommandFinished listener, which runs before this one.
        $this->logger->log(report: ! $this->attributes->has($classes, DontReportQueries::class));
    }
}
