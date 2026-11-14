<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter\Tests\Fixtures\Commands;

class PlainCommand extends InsertUserCommand
{
    protected $signature = 'sql-reporter:plain {--call=}';
}
