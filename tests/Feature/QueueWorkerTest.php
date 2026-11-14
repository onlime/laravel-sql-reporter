<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Onlime\LaravelSqlReporter\Events\QueryLogWritten;
use Onlime\LaravelSqlReporter\Tests\Fixtures\InsertUserJob;

/*
 * End-to-end tests of a queue worker processing a database queue on the in-memory SQLite database.
 */

beforeEach(function () {
    // Laravel only dispatches CommandFinished outside of unit tests
    $this->app->make(Kernel::class)->rerouteSymfonyCommandEvents();

    $this->directory = sys_get_temp_dir().'/laravel-sql-reporter-tests/'.uniqid('', true);
    $this->logFile = $this->directory.'/query-log.sql';
    config([
        'sql-reporter.general.directory' => $this->directory,
        'sql-reporter.queries.file_name' => 'query-log',
        'queue.default' => 'database',
        'queue.connections.database.connection' => null,
        'queue.failed.driver' => 'null',
        'logging.default' => 'null',
    ]);

    Schema::create('jobs', function (Blueprint $table) {
        $table->id();
        $table->string('queue')->index();
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
    DB::flushQueryLog();
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->directory);
});

it('logs the queries of each queued job separately', function () {
    config(['sql-reporter.queries.log_jobs' => true]);
    InsertUserJob::dispatch('Alice');
    InsertUserJob::dispatch('Bob');

    $this->artisan('queue:work', ['--stop-when-empty' => true, '--sleep' => 0])->assertSuccessful();

    $log = file_get_contents($this->logFile);
    [, $alice, $bob] = explode('-- Origin:   (job) '.InsertUserJob::class.PHP_EOL, $log);

    expect(substr_count($log, '-- Origin:'))->toBe(2)
        // each job starts with an empty query log, so its first query is not the worker reserving the job
        ->and($alice)->toMatch('/^-- Query 1 \[[\d.]+ms\]\ninsert into "users" \("name"\) values \(\'Alice\'\);$/m')
        ->and($bob)->toMatch('/^-- Query 1 \[[\d.]+ms\]\ninsert into "users" \("name"\) values \(\'Bob\'\);$/m')
        ->and($alice)->not->toContain('Bob');
});

it('dispatches a QueryLogWritten event per job', function () {
    config(['sql-reporter.queries.log_jobs' => true]);
    $events = [];
    Event::listen(QueryLogWritten::class, function (QueryLogWritten $event) use (&$events) {
        $events[] = $event;
    });
    InsertUserJob::dispatch('Alice');
    InsertUserJob::dispatch('Bob');

    $this->artisan('queue:work', ['--stop-when-empty' => true, '--sleep' => 0])->assertSuccessful();

    expect($events)->toHaveCount(2)
        ->and($events[0]->reportHeader)->toContain('-- Origin:   (job) '.InsertUserJob::class.PHP_EOL)
        ->and($events[0]->reportQueries[0])->toContain('insert into "users" ("name") values (\'Alice\');')
        ->and($events[1]->reportQueries[0])->toContain('insert into "users" ("name") values (\'Bob\');');
});

it('logs failed job attempts', function () {
    config(['sql-reporter.queries.log_jobs' => true]);
    InsertUserJob::dispatch('Alice', fail: true);

    $this->artisan('queue:work', ['--stop-when-empty' => true, '--sleep' => 0, '--tries' => 1])->assertSuccessful();

    expect(file_get_contents($this->logFile))
        ->toContain('-- Origin:   (job) '.InsertUserJob::class.PHP_EOL)
        ->toContain('insert into "users" ("name") values (\'Alice\');');
});

it('does not log queued jobs by default, but keeps the query log of the worker empty', function () {
    InsertUserJob::dispatch('Alice');
    InsertUserJob::dispatch('Bob');

    $this->artisan('queue:work', ['--stop-when-empty' => true, '--sleep' => 0])->assertSuccessful();

    // neither the jobs nor the worker command itself got logged
    expect(DB::getQueryLog())->toBe([])
        ->and($this->logFile)->not->toBeFile()
        ->and(DB::table('users')->pluck('name')->all())->toBe(['Alice', 'Bob']);
});

it('logs jobs on a sync queue as part of the dispatching command', function () {
    config([
        'sql-reporter.queries.log_jobs' => true,
        'queue.default' => 'sync',
    ]);
    Artisan::command('sql-reporter:dispatch', function () {
        InsertUserJob::dispatch('Alice');
        DB::table('users')->insert(['name' => 'Bob']);
    });

    $this->artisan('sql-reporter:dispatch')->assertSuccessful();

    $log = file_get_contents($this->logFile);
    expect(substr_count($log, '-- Origin:'))->toBe(1)
        ->and($log)->toContain('-- Origin:   (console) ')
        ->toMatch('/^-- Query 1 \[[\d.]+ms\]\ninsert into "users" \("name"\) values \(\'Alice\'\);$/m')
        ->toMatch('/^-- Query 2 \[[\d.]+ms\]\ninsert into "users" \("name"\) values \(\'Bob\'\);$/m');
});
