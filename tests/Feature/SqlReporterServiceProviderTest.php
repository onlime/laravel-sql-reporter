<?php

declare(strict_types=1);

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobTimedOut;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Onlime\LaravelSqlReporter\Config;
use Onlime\LaravelSqlReporter\Listeners\LogJobQueries;
use Onlime\LaravelSqlReporter\Listeners\LogSqlQuery;
use Onlime\LaravelSqlReporter\Providers\SqlReporterServiceProvider;

/**
 * @return array<class-string, class-string>
 */
function listenedEvents(): array
{
    return [
        CommandStarting::class => LogSqlQuery::class,
        CommandFinished::class => LogSqlQuery::class,
        RequestHandled::class => LogSqlQuery::class,
        Looping::class => LogJobQueries::class,
        JobProcessing::class => LogJobQueries::class,
        JobAttempted::class => LogJobQueries::class,
        JobTimedOut::class => LogJobQueries::class,
    ];
}

it('registers event listeners', function (string $event, string $listener) {
    expect(Event::getRawListeners()[$event] ?? [])->toContain($listener);
})->with(array_map(null, array_keys(listenedEvents()), listenedEvents()));

it('enables the query log', function () {
    expect(DB::logging())->toBeTrue();
});

it('neither registers the listener nor enables the query log if queries are disabled', function () {
    config(['sql-reporter.queries.enabled' => false]);
    $events = array_keys(listenedEvents());
    array_map(Event::forget(...), $events);
    DB::disableQueryLog();

    $provider = new SqlReporterServiceProvider($this->app);
    $provider->register();
    $provider->boot();

    foreach ($events as $event) {
        expect(Event::hasListeners($event))->toBeFalse();
    }
    expect(DB::logging())->toBeFalse();
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

it('neither resolves the client hostname nor its country by default, and redacts sensitive URL parameters', function () {
    expect(config('sql-reporter.formatting.header_fields'))
        ->toBe(['datetime', 'origin', 'route', 'status', 'user', 'guard', 'env', 'agent', 'ip', 'referer'])
        ->and(config('sql-reporter.formatting.geoip'))->toBeFalse()
        ->and(config('sql-reporter.formatting.redact_url_parameters'))->toBe(Config::DEFAULT_REDACT_URL_PARAMETERS);
});

it('redacts the values of sensitive columns by default', function () {
    expect(config('sql-reporter.queries.redact_columns'))->toBe(Config::DEFAULT_REDACT_COLUMNS);
});

it('reads the file name from SQL_REPORTER_LOG_FILE_NAME, or the former SQL_REPORTER_QUERIES_FILE_NAME', function (array $env, string $expected) {
    $_SERVER = array_merge($_SERVER, $env);

    try {
        $config = require dirname(__DIR__, 2).'/config/sql-reporter.php';
        expect($config['general']['log_file_name'])->toBe($expected);
    } finally {
        foreach (array_keys($env) as $name) {
            unset($_SERVER[$name]);
        }
    }
})->with([
    'none (default)' => [[], '[Y-m]-log'],
    'the new name' => [['SQL_REPORTER_LOG_FILE_NAME' => 'new-log'], 'new-log'],
    'the former name' => [['SQL_REPORTER_QUERIES_FILE_NAME' => 'former-log'], 'former-log'],
    'both, the new name wins' => [['SQL_REPORTER_LOG_FILE_NAME' => 'new-log', 'SQL_REPORTER_QUERIES_FILE_NAME' => 'former-log'], 'new-log'],
]);

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
