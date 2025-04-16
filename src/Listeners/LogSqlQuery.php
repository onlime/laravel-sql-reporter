<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter\Listeners;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\App;
use Onlime\LaravelSqlReporter\SqlLogger;

readonly class LogSqlQuery
{
    /**
     * Create the event listener.
     */
    public function __construct(
        private SqlLogger $logger,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(CommandFinished|RequestHandled $event): void
    {
        // Prevent duplicate logging when running in request (RequestHandled event)
        // and programmatically executing Artisan commands (CommandFinished event).
        if ($event instanceof RequestHandled === App::runningInConsole()) {
            return;
        }

        // Push the logging to the background, after the response has been sent.
        defer(fn () => $this->logger->log())->always();
    }
}
