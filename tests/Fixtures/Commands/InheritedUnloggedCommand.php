<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter\Tests\Fixtures\Commands;

/**
 * Inherits the DontLogQueries attribute of its parent class.
 */
class InheritedUnloggedCommand extends UnloggedCommand
{
    protected $signature = 'sql-reporter:inherited-unlogged {--call=}';
}
