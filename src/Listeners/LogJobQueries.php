<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter\Listeners;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobTimedOut;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\DB;
use Onlime\LaravelSqlReporter\Attributes\DontLogQueries;
use Onlime\LaravelSqlReporter\Attributes\DontReportQueries;
use Onlime\LaravelSqlReporter\Config;
use Onlime\LaravelSqlReporter\SqlLogger;
use Onlime\LaravelSqlReporter\Support\AttributeResolver;

/**
 * Handles the query log of long-running queue workers, which would otherwise collect the queries of all processed
 * jobs in memory until the worker stops. Optionally logs the queries of each job separately.
 */
readonly class LogJobQueries
{
    public function __construct(
        private Config $config,
        private SqlLogger $logger,
        private AttributeResolver $attributes,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(Looping|JobProcessing|JobAttempted|JobTimedOut $event): void
    {
        // Jobs on a sync queue (or the deferred / background queues based on it) run within the current
        // request or command, so their queries are logged as part of it.
        if (($event instanceof JobProcessing || $event instanceof JobAttempted) && $event->job instanceof SyncJob) {
            return;
        }

        // A timed out job is killed right after the JobTimedOut event, without a JobAttempted event
        if (($event instanceof JobAttempted || $event instanceof JobTimedOut) && $this->config->queriesLogJobs()) {
            $this->logJob($event->job);
        }

        // Start each job with an empty query log, and don't collect the queries of the worker itself
        // between jobs (e.g. polling a database queue).
        DB::flushQueryLog();
    }

    /**
     * Log the queries of a job, unless it is excluded by the DontLogQueries attribute.
     */
    private function logJob(Job $job): void
    {
        $name = $job->resolveName();
        // the display name is the class of the job, queued listener, notification or mailable,
        // the queued job class may be e.g. CallQueuedListener
        $classes = array_filter([$name, $job->resolveQueuedJobClass()], class_exists(...));

        if (! $this->attributes->has($classes, DontLogQueries::class)) {
            $this->logger->log(
                origin: '(job) '.$name,
                report: ! $this->attributes->has($classes, DontReportQueries::class),
            );
        }
    }
}
