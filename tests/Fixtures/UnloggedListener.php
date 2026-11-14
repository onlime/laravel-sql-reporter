<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter\Tests\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Onlime\LaravelSqlReporter\Attributes\DontLogQueries;

#[DontLogQueries]
class UnloggedListener implements ShouldQueue
{
    public function handle(string $name): void
    {
        DB::table('users')->insert(['name' => $name]);
    }
}
