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
     *
     * @param Writer $writer
     */
    public function __construct(
        private Writer $writer
    ) {}

    /**
     * Log queries
     */
    public function log()
    {
        foreach (DB::getQueryLog() as $query) {
            $sqlQuery = new SqlQuery(++$this->queryNumber, $query['query'], $query['bindings'], $query['time']);
            $this->writer->writeQuery($sqlQuery);
        }
    }
}
