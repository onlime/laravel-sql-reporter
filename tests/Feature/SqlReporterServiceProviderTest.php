<?php

declare(strict_types=1);

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;
use Onlime\LaravelSqlReporter\Listeners\LogSqlQuery;

it('registers event listeners', function (string $eventName) {
    $listeners = Event::getRawListeners()[$eventName] ?? [];

    expect($listeners)->not->toBeEmpty()
        ->and($listeners)->toContain(LogSqlQuery::class);
})->with([
    CommandFinished::class,
    RequestHandled::class,
]);

it('merges the default config', function () {
    $config = config('sql-reporter');

    expect($config)->toBeArray()
        ->and($config)->toHaveKey('queries')
        ->and($config)->toHaveKey('general');
});

it('can publish the config file', function () {
    $published = config_path('sql-reporter.php');
    @unlink($published);

    try {
        $this->artisan('vendor:publish', ['--tag' => 'sql-reporter'])->assertSuccessful();

        expect($published)->toBeFile()
            ->and(file_get_contents($published))->toBe(file_get_contents(__DIR__.'/../../config/sql-reporter.php'));
    } finally {
        // don't leave the published config in the Testbench skeleton, where it would be loaded by later tests
        @unlink($published);
    }
});
