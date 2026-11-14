<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter\Events;

use Illuminate\Foundation\Events\Dispatchable;

class QueryLogWritten
{
    use Dispatchable;

    /**
     * @param  list<string>  $reportQueries
     */
    public function __construct(
        public int $loggedQueryCount,
        public string $reportHeader,
        public array $reportQueries,
    ) {}
}
