<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Onlime\LaravelSqlReporter\Support\RawQuery;
use Onlime\LaravelSqlReporter\Support\Redactor;
use Throwable;

class SqlLogger
{
    /**
     * SqlLogger constructor.
     */
    public function __construct(
        private Writer $writer,
        private Redactor $redactor,
    ) {}

    /**
     * Log queries
     *
     * @param  string|null  $origin  shown in the log header instead of the request or console command, e.g. a queued job
     * @param  bool  $report  whether to dispatch the QueryLogWritten event for the queries to report
     */
    public function log(?string $origin = null, bool $report = true): void
    {
        try {
            if ($origin !== null) {
                $this->writer->setOrigin($origin);
            }

            $connection = DB::connection();
            $rawQuery = new RawQuery($connection, $this->redactor);

            // Not using getRawQueryLog(), which fails as a whole if a single value can't be escaped
            Collection::make($connection->getQueryLog())
                ->map(fn (array $query, int $key) => new SqlQuery(
                    $key + 1,
                    $rawQuery->build($query['query'], $query['bindings']),
                    // the time is optional for queries logged with Connection::logQuery()
                    $query['time'] ?? 0.0,
                    $query['query'],
                    $query['bindings']
                ))
                ->each(function (SqlQuery $query) {
                    $this->writer->writeQuery($query);
                });
            $this->writer->flush();

            if ($report) {
                $this->writer->report();
            }
        } catch (Throwable $e) {
            // Logging must never break the request, command or job, e.g. if the log file is not writable
            // or a QueryLogWritten listener fails. Report the error instead.
            report($e);
        }
    }
}
