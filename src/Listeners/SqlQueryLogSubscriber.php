<?php

namespace Onlime\LaravelSqlReporter\Listeners;

use Illuminate\Console\Events\CommandFinished;
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
    ) {}

    /**
     * Register the listeners for the subscriber.
     *
     * @param \Illuminate\Events\Dispatcher $events
     * @return void
     */
    public function subscribe($events)
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
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        $this->logger->log();
    }
}
