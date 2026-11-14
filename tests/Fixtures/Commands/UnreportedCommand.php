<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter\Tests\Fixtures\Commands;

use Onlime\LaravelSqlReporter\Attributes\DontReportQueries;

#[DontReportQueries]
class UnreportedCommand extends InsertUserCommand
{
    protected $signature = 'sql-reporter:unreported {--call=}';
}
