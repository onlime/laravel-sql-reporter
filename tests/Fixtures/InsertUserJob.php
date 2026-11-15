<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter\Tests\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InsertUserJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $name,
        public bool $fail = false,
    ) {}

    public function handle(): void
    {
        DB::table('users')->insert(['name' => $this->name]);

        if ($this->fail) {
            throw new RuntimeException('Job failed');
        }
    }
}
