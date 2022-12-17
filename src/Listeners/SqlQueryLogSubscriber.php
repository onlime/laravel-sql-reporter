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
     *
     * @return void
     */
    public function __construct(
        private SqlLogger $logger,
    ) {
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @param Dispatcher $events
     * @return void
     */
    public function subscribe(Dispatcher $events)
    {
        $events->listen(
            [
                RequestHandled::class,
                CommandFinished::class,
            ],
            [SqlQueryLogSubscriber::class, 'handle']
        );
    }

    /**
     * Handle the event.
     */
    public function handle($event)
    {
        $this->logger->log();
    }
}
