<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SqlLogger
{
    /**
     * SqlLogger constructor.
     */
    public function __construct(
        private Writer $writer
    ) {}

    /**
     * Log queries
     *
     * @param  string|null  $origin  shown in the log header instead of the request or console command, e.g. a queued job
     * @param  bool  $report  whether to dispatch the QueryLogWritten event for the queries to report
     */
    public function log(?string $origin = null, bool $report = true): void
    {
        if ($origin !== null) {
            $this->writer->setOrigin($origin);
        }

        $queryLog = DB::getQueryLog();

        // getQueryLog() and getRawQueryLog() have the same keys
        // see \Illuminate\Database\Connection::getRawQueryLog()
        Collection::make(DB::getRawQueryLog())
            ->map(fn (array $query, int $key) => new SqlQuery(
                $key + 1,
                $query['raw_query'],
                $query['time'],
                $queryLog[$key]['query'],
                $queryLog[$key]['bindings']
            ))
            ->each(function (SqlQuery $query) {
                $this->writer->writeQuery($query);
            });

        if ($report) {
            $this->writer->report();
        }
    }
}
