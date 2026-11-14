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
    /**
     * Whether the query log was enabled while the command was running.
     */
    public static ?bool $queryLogEnabled = null;

    public function handle(): void
    {
        self::$queryLogEnabled = DB::logging();

        DB::table('users')->insert(['name' => (string) $this->getName()]);

        if ($command = $this->option('call')) {
            Artisan::call((string) $command);
        }
    }
}
