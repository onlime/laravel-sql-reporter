<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Onlime\LaravelSqlReporter\Events\QueryLogWritten;
use Onlime\LaravelSqlReporter\SqlQuery;
use Onlime\LaravelSqlReporter\Tests\Fixtures\Commands\InheritedUnloggedCommand;
use Onlime\LaravelSqlReporter\Tests\Fixtures\Commands\InsertUserCommand;
use Onlime\LaravelSqlReporter\Tests\Fixtures\Commands\PlainCommand;
use Onlime\LaravelSqlReporter\Tests\Fixtures\Commands\UnloggedCommand;
use Onlime\LaravelSqlReporter\Tests\Fixtures\Commands\UnreportedCommand;
use Onlime\LaravelSqlReporter\Writer;

/*
 * End-to-end tests: real queries on an in-memory SQLite database, logged into a real log file
 * by the package's CommandFinished / RequestHandled listener.
 */

beforeEach(function () {
    // No withoutDefer(): requests are logged by a deferred callback, which must be invoked by Laravel.
    // Laravel only dispatches CommandFinished outside of unit tests
    $this->app->make(Kernel::class)->rerouteSymfonyCommandEvents();

    $this->directory = sys_get_temp_dir().'/laravel-sql-reporter-tests/'.uniqid('', true);
    $this->logFile = $this->directory.'/query-log.sql';
    config([
        'sql-reporter.general.directory' => $this->directory,
        'sql-reporter.queries.file_name' => 'query-log',
    ]);

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
    DB::flushQueryLog();

    Artisan::command('sql-reporter:test', function () {
        DB::table('users')->insert(['name' => 'Alice']);
        DB::table('users')->where('name', 'Alice')->first();
    });
    InsertUserCommand::$queryLogEnabled = null;
    foreach ([PlainCommand::class, UnloggedCommand::class, InheritedUnloggedCommand::class, UnreportedCommand::class] as $command) {
        $this->app->make(Kernel::class)->registerCommand(new $command);
    }

    $this->reported = [];
    Event::listen(QueryLogWritten::class, function (QueryLogWritten $event) {
        $this->reported[] = $event;
    });
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->directory);
});

/**
 * Let the application behave as if it was serving an HTTP request instead of running in console.
 */
function serveHttpRequests(Application $app): void
{
    (function () {
        $this->isRunningInConsole = false;
    })->call($app);
}

it('logs the queries of an Artisan command', function () {
    $this->artisan('sql-reporter:test')->assertSuccessful();

    expect($this->logFile)->toBeFile()
        ->and(file_get_contents($this->logFile))
        ->toStartWith('-- --------------------------------------------------'.PHP_EOL.'-- Datetime: ')
        ->toContain('-- Origin:   (console) ')
        ->toContain('-- Status:   Executed 2 queries in ')
        ->toContain('-- Env:      testing')
        ->toMatch('/^-- Query 1 \[[\d.]+ms\]\ninsert into "users" \("name"\) values \(\'Alice\'\);$/m')
        ->toMatch('/^-- Query 2 \[[\d.]+ms\]\nselect \* from "users" where "name" = \'Alice\' limit 1;$/m');
});

it('logs the queries of an HTTP request', function () {
    serveHttpRequests($this->app);
    Route::get('/users', fn () => DB::table('users')->get());

    $this->get('/users?page=2', ['User-Agent' => 'Pest', 'Referer' => 'https://example.com/'])->assertOk();

    expect(file_get_contents($this->logFile))
        ->toContain('-- Origin:   (request) GET http://localhost/users?page=2')
        ->toContain('-- Status:   Executed 1 queries in ')
        ->toContain('-- Agent:    Pest')
        ->toContain('-- Ip:       127.0.0.1')
        ->toContain('-- Referer:  https://example.com/')
        ->toMatch('/^-- Query 1 \[[\d.]+ms\]\nselect \* from "users";$/m');
});

it('logs and reports a request with values that cannot be escaped as a string', function (string $search, string $literal) {
    // Such a value used to make the whole log of the request fail, so a client could keep its queries from being
    // logged and reported, simply by sending a null byte or invalid UTF-8 in any parameter.
    Exceptions::fake();
    serveHttpRequests($this->app);
    Route::get('/search', function () {
        DB::table('users')->insert(['name' => 'Mallory']);

        return DB::table('users')->where('name', request('q'))->get();
    });

    $this->get('/search?q='.rawurlencode($search))->assertOk();

    expect(file_get_contents($this->logFile))
        ->toContain('-- Status:   Executed 2 queries in ')
        ->toContain('insert into "users" ("name") values (\'Mallory\');')
        ->toContain("select * from \"users\" where \"name\" = {$literal};")
        ->and($this->reported)->toHaveCount(1);
    Exceptions::assertNothingReported();
})->with([
    'null byte' => ["a\0b", "x'610062'"],
    'invalid UTF-8' => ["\xff\xfe", "x'fffe'"],
]);

it('logs an Artisan command called during an HTTP request only once', function () {
    serveHttpRequests($this->app);
    Route::get('/call', function () {
        Artisan::call('sql-reporter:test');

        return 'ok';
    });

    $this->get('/call')->assertOk();

    $log = file_get_contents($this->logFile);
    expect(substr_count($log, '-- Origin:'))->toBe(1)
        ->and($log)->toContain('-- Origin:   (request) GET http://localhost/call');
});

it('logs Artisan commands to a separate file if a console suffix is configured', function () {
    config(['sql-reporter.general.console_log_suffix' => '-artisan']);

    $this->artisan('sql-reporter:test')->assertSuccessful();

    expect($this->directory.'/query-log-artisan.sql')->toBeFile()
        ->and($this->logFile)->not->toBeFile();
});

it('dispatches the QueryLogWritten event with the queries to report', function () {
    $events = [];
    Event::listen(QueryLogWritten::class, function (QueryLogWritten $event) use (&$events) {
        $events[] = $event;
    });

    $this->artisan('sql-reporter:test')->assertSuccessful();

    expect($events)->toHaveCount(1)
        ->and($events[0]->loggedQueryCount)->toBe(2)
        ->and($events[0]->reportHeader)->toContain('-- Origin:   (console) ')
        // the select query is excluded by the default report pattern
        ->and($events[0]->reportQueries)->toHaveCount(1)
        ->and($events[0]->reportQueries[0])->toMatch('/^-- Query 1 \[[\d.]+ms\]\ninsert into "users" \("name"\) values \(\'Alice\'\);$/');
});

it('passes the query with its bindings to the report callback', function () {
    $queries = [];
    Writer::shouldReportQuery(function (SqlQuery $query) use (&$queries) {
        $queries[] = $query;

        return true;
    });

    $this->artisan('sql-reporter:test')->assertSuccessful();

    // the callback is only called for queries matching the report pattern
    expect($queries)->toHaveCount(1)
        ->and($queries[0]->number)->toBe(1)
        ->and($queries[0]->query)->toBe('insert into "users" ("name") values (?)')
        ->and($queries[0]->bindings)->toBe(['Alice'])
        ->and($queries[0]->rawQuery)->toBe('insert into "users" ("name") values (\'Alice\')')
        ->and($queries[0]->time)->toBeFloat();
});

it('does not log Artisan commands with the DontLogQueries attribute', function (string $command) {
    $this->artisan($command)->assertSuccessful();

    expect(DB::table('users')->pluck('name')->all())->toBe([$command])
        ->and($this->logFile)->not->toBeFile()
        ->and($this->reported)->toBeEmpty()
        // the query log is disabled while the command runs, so it doesn't collect its queries in memory
        ->and(InsertUserCommand::$queryLogEnabled)->toBeFalse()
        ->and(DB::logging())->toBeTrue();
})->with([
    'attribute on the command' => 'sql-reporter:unlogged',
    'attribute on a parent class' => 'sql-reporter:inherited-unlogged',
]);

it('logs, but does not report Artisan commands with the DontReportQueries attribute', function () {
    $this->artisan('sql-reporter:unreported')->assertSuccessful();

    expect(file_get_contents($this->logFile))
        ->toContain('-- Origin:   (console) ')
        ->toContain('insert into "users" ("name") values (\'sql-reporter:unreported\');')
        ->and($this->reported)->toBeEmpty();
});

it('ignores the attributes of an Artisan command called during an HTTP request', function () {
    serveHttpRequests($this->app);
    Route::get('/call', function () {
        Artisan::call('sql-reporter:unlogged');

        return 'ok';
    });

    $this->get('/call')->assertOk();

    // the request is the unit being logged
    expect(file_get_contents($this->logFile))
        ->toContain('-- Origin:   (request) GET http://localhost/call')
        ->toContain('insert into "users" ("name") values (\'sql-reporter:unlogged\');')
        ->and($this->reported)->toHaveCount(1);
});

it('logs an Artisan command calling another one only once, as part of the outer command', function () {
    $this->artisan('sql-reporter:plain', ['--call' => 'sql-reporter:test'])->assertSuccessful();

    $log = file_get_contents($this->logFile);
    expect(substr_count($log, '-- Origin:'))->toBe(1)
        ->and($log)->toContain('-- Status:   Executed 3 queries in ')
        ->toContain('insert into "users" ("name") values (\'sql-reporter:plain\');')
        ->toContain('insert into "users" ("name") values (\'Alice\');')
        ->and($this->reported)->toHaveCount(1);
});

it('applies the attributes of the outermost command to the commands it calls', function () {
    $this->artisan('sql-reporter:unlogged', ['--call' => 'sql-reporter:test'])->assertSuccessful();

    expect(DB::table('users')->count())->toBe(2)
        ->and($this->logFile)->not->toBeFile()
        ->and($this->reported)->toBeEmpty();
});

it('does not log Artisan commands excluded by the exclude_commands config option', function (string $pattern) {
    config(['sql-reporter.queries.exclude_commands' => ['pulse:check', $pattern]]);

    $this->artisan('sql-reporter:plain')->assertSuccessful();

    expect(DB::table('users')->count())->toBe(1)
        ->and($this->logFile)->not->toBeFile()
        ->and($this->reported)->toBeEmpty()
        ->and(InsertUserCommand::$queryLogEnabled)->toBeFalse()
        ->and(DB::logging())->toBeTrue();
})->with([
    'command name' => 'sql-reporter:plain',
    'command name with wildcard' => 'sql-reporter:pl*',
    'class name' => PlainCommand::class,
    'class name with wildcard' => 'Onlime\LaravelSqlReporter\Tests\Fixtures\Commands\Plain*',
    'parent class name' => InsertUserCommand::class,
]);

it('logs Artisan commands not matching the exclude_commands config option', function () {
    config(['sql-reporter.queries.exclude_commands' => ['sql-reporter:unlogged', 'Laravel\Pulse\*']]);

    $this->artisan('sql-reporter:plain')->assertSuccessful();

    expect(file_get_contents($this->logFile))->toContain('insert into "users" ("name") values (\'sql-reporter:plain\');')
        ->and(InsertUserCommand::$queryLogEnabled)->toBeTrue();
});

it('reports logging errors instead of failing the command', function () {
    Exceptions::fake();
    // a directory below a file can't be created
    $file = tempnam(sys_get_temp_dir(), 'sql-reporter');
    config(['sql-reporter.general.directory' => $file.'/sql']);

    try {
        $this->artisan('sql-reporter:test')->assertSuccessful();
    } finally {
        unlink($file);
    }

    Exceptions::assertReported(ErrorException::class);
});

it('reports a failing QueryLogWritten listener instead of failing the command', function () {
    Exceptions::fake();
    Event::listen(QueryLogWritten::class, function () {
        throw new RuntimeException('Listener failed');
    });

    $this->artisan('sql-reporter:test')->assertSuccessful();

    Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'Listener failed');
    expect($this->logFile)->toBeFile();
});
