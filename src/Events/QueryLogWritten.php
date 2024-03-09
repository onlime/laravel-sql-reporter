<?php

namespace Onlime\LaravelSqlReporter\Events;

use Illuminate\Foundation\Events\Dispatchable;

class QueryLogWritten
{
    use Dispatchable;

    public function __construct(
        public int $loggedQueryCount,
        public string $reportHeader,
        public array $reportQueries,
    ) {
    }
}
