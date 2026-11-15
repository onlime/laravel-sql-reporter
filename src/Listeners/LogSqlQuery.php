<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter\Listeners;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Onlime\LaravelSqlReporter\Attributes\DontLogQueries;
use Onlime\LaravelSqlReporter\Attributes\DontReportQueries;
use Onlime\LaravelSqlReporter\Config;
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
        private Config $config,
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
        if (! $outermost) {
            return;
        }

        $class = $this->attributes->commandClass($event->command);
        $excluded = $this->isExcluded($event->command, $class);

        if ($event instanceof CommandStarting) {
            if ($excluded) {
                // Don't even collect the queries of an excluded command, which might be long-running (e.g. a daemon)
                DB::flushQueryLog();
                DB::disableQueryLog();
            }

            return;
        }

        if ($excluded) {
            DB::enableQueryLog();

            return;
        }

        // A queue worker logs the queries of each job separately, but not its own (e.g. polling a database queue)
        if ($this->state->isQueueWorker()) {
            return;
        }

        // Log right away, as there is no response to be sent first. A deferred callback would never run, because
        // Laravel invokes deferred callbacks in its own CommandFinished listener, which runs before this one.
        $this->logger->log(report: ! $this->attributes->has($class, DontReportQueries::class));
    }

    /**
     * Whether the command is excluded by the DontLogQueries attribute or the exclude_commands config option.
     *
     * @param  class-string|null  $class
     */
    private function isExcluded(string $name, ?string $class): bool
    {
        if ($this->attributes->has($class, DontLogQueries::class)) {
            return true;
        }

        $patterns = $this->config->queriesExcludeCommands();

        // the command name, with wildcards
        if (Str::is($patterns, $name)) {
            return true;
        }

        // the class name, with wildcards, or the name of a parent class
        return $class !== null
            && (Str::is($patterns, $class) || array_intersect($patterns, class_parents($class)) !== []);
    }
}
