<?php

namespace Onlime\LaravelSqlReporter\Listeners;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Onlime\LaravelSqlReporter\SqlLogger;

class LogSqlQuery
{
    /**
     * Create the event listener.
     */
    public function __construct(
        private SqlLogger $logger,
    ) {
    }

    /**
     * Handle the event.
     */
    public function handle(CommandFinished|RequestHandled $event): void
    {
        $this->logger->log();
    }
}
