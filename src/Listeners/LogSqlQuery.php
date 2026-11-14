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

        $classes = $this->attributes->commandClasses($event->command);
        $excluded = $this->isExcluded($event->command, $classes);

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
        $this->logger->log(report: ! $this->attributes->has($classes, DontReportQueries::class));
    }

    /**
     * Whether the command is excluded by the DontLogQueries attribute or the exclude_commands config option.
     *
     * @param  list<class-string>  $classes
     */
    private function isExcluded(?string $name, array $classes): bool
    {
        if ($this->attributes->has($classes, DontLogQueries::class)) {
            return true;
        }

        // command names or class names, both with wildcards, and parent classes
        foreach ($this->config->queriesExcludeCommands() as $pattern) {
            if (Str::is($pattern, $name ?? '')) {
                return true;
            }
            foreach ($classes as $class) {
                if (Str::is($pattern, $class) || is_a($class, $pattern, true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
