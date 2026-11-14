<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter\Tests\Fixtures\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Inserts a user named like the command, and optionally calls another command (--call).
 */
abstract class InsertUserCommand extends Command
{
    public function handle(): void
    {
        DB::table('users')->insert(['name' => (string) $this->getName()]);

        if ($command = $this->option('call')) {
            Artisan::call((string) $command);
        }
    }
}
