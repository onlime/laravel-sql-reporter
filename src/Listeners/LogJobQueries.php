<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter\Listeners;

use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\DB;
use Onlime\LaravelSqlReporter\Config;
use Onlime\LaravelSqlReporter\SqlLogger;

/**
 * Handles the query log of long-running queue workers, which would otherwise collect the queries of all processed
 * jobs in memory until the worker stops. Optionally logs the queries of each job separately.
 */
readonly class LogJobQueries
{
    public function __construct(
        private Config $config,
        private SqlLogger $logger,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(Looping|JobProcessing|JobAttempted|WorkerStopping $event): void
    {
        // Jobs on a sync queue (or the deferred / background queues based on it) run within the current
        // request or command, so their queries are logged as part of it.
        if (($event instanceof JobProcessing || $event instanceof JobAttempted) && $event->job instanceof SyncJob) {
            return;
        }

        if ($event instanceof JobAttempted && $this->config->queriesLogJobs()) {
            $this->logger->log('(job) '.$event->job->resolveName());
        }

        // Start each job with an empty query log, and don't collect the queries of the worker itself
        // (e.g. polling a database queue), neither between jobs nor when it stops.
        DB::flushQueryLog();
    }
}
