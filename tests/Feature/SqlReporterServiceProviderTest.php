<?php

declare(strict_types=1);

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Onlime\LaravelSqlReporter\Listeners\LogSqlQuery;
use Onlime\LaravelSqlReporter\Providers\SqlReporterServiceProvider;

it('registers event listeners', function (string $eventName) {
    $listeners = Event::getRawListeners()[$eventName] ?? [];

    expect($listeners)->not->toBeEmpty()
        ->and($listeners)->toContain(LogSqlQuery::class);
})->with([
    CommandFinished::class,
    RequestHandled::class,
]);

it('enables the query log', function () {
    expect(DB::logging())->toBeTrue();
});

it('neither registers the listener nor enables the query log if queries are disabled', function () {
    config(['sql-reporter.queries.enabled' => false]);
    Event::forget(CommandFinished::class);
    Event::forget(RequestHandled::class);
    DB::disableQueryLog();

    $provider = new SqlReporterServiceProvider($this->app);
    $provider->register();
    $provider->boot();

    expect(Event::hasListeners(CommandFinished::class))->toBeFalse()
        ->and(Event::hasListeners(RequestHandled::class))->toBeFalse()
        ->and(DB::logging())->toBeFalse();
});

it('excludes long-running vendor commands by default', function () {
    expect(config('sql-reporter.queries.exclude_commands'))->toBe([
        'Illuminate\\Console\\Scheduling\\ScheduleWorkCommand',
        'Laravel\\Horizon\\Console\\HorizonCommand',
        'Laravel\\Horizon\\Console\\SupervisorCommand',
        'Laravel\\Pulse\\Commands\\CheckCommand',
        'Laravel\\Pulse\\Commands\\WorkCommand',
        'Laravel\\Reverb\\Servers\\Reverb\\Console\\Commands\\StartServer',
    ]);
});

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
