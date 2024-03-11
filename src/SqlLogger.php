<?php

namespace Onlime\LaravelSqlReporter;

use Illuminate\Support\Facades\DB;

class SqlLogger
{
    /**
     * Number of executed queries.
     */
    private int $queryNumber = 0;

    /**
     * SqlLogger constructor.
     */
    public function __construct(
        private Writer $writer
    ) {
    }

    /**
     * Log queries
     */
    public function log(): void
    {
        foreach (DB::getRawQueryLog() as $query) {
            $this->writer->writeQuery(
                new SqlQuery(++$this->queryNumber, $query['raw_query'], $query['time'])
            );
        }
        $this->writer->report();
    }
}
