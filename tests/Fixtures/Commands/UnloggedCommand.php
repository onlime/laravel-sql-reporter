<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter\Tests\Fixtures\Commands;

use Onlime\LaravelSqlReporter\Attributes\DontLogQueries;

#[DontLogQueries]
class UnloggedCommand extends InsertUserCommand
{
    protected $signature = 'sql-reporter:unlogged {--call=}';
}
