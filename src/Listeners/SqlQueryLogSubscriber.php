<?php

namespace Onlime\LaravelSqlReporter\Listeners;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Onlime\LaravelSqlReporter\SqlLogger;

class SqlQueryLogSubscriber
{
    /**
     * Create the event listener.
     */
    public function __construct(
        private SqlLogger $logger,
    ) {
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            [
                RequestHandled::class,
                CommandFinished::class,
            ],
            [self::class, 'handle']
        );
    }

    /**
     * Handle the event.
     */
    public function handle($event): void
    {
        $this->logger->log();
    }
}
