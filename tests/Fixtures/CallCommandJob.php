<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter\Tests\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

class CallCommandJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $command,
    ) {}

    public function handle(): void
    {
        Artisan::call($this->command);
    }
}
