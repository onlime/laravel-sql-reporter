<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter\Tests\Fixtures\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Inserts a user named like the command.
 */
abstract class InsertUserCommand extends Command
{
    public function handle(): void
    {
        DB::table('users')->insert(['name' => (string) $this->getName()]);
    }
}
