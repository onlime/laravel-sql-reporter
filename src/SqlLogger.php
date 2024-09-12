<?php

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
     */
    public function log(): void
    {
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

        $this->writer->report();
    }
}
