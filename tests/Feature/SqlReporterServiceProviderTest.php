<?php

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;
use Onlime\LaravelSqlReporter\Listeners\LogSqlQuery;

it('registers event listeners', function (string $eventName) {
    $listeners = Event::getRawListeners()[$eventName] ?? [];

    expect($listeners)->not->toBeEmpty();
    expect($listeners)->toContain(LogSqlQuery::class);
})->with([
    CommandFinished::class,
    RequestHandled::class,
]);

it('merges the default config', function () {
    $config = config('sql-reporter');

    expect($config)->toBeArray();
    expect($config)->toHaveKey('queries');
    expect($config)->toHaveKey('general');
});

it('can publish the config file', function () {
    @unlink(config_path('sql-reporter.php'));

    $this->artisan('vendor:publish', ['--tag' => 'sql-reporter']);

    $this->assertFileExists(config_path('sql-reporter.php'));
});
