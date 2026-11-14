<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Event;
use Onlime\LaravelSqlReporter\Config;
use Onlime\LaravelSqlReporter\Events\QueryLogWritten;
use Onlime\LaravelSqlReporter\FileName;
use Onlime\LaravelSqlReporter\Formatter;
use Onlime\LaravelSqlReporter\SqlQuery;
use Onlime\LaravelSqlReporter\Writer;

beforeEach(function () {
    $this->now = Carbon::parse('2015-02-03 06:41:31');
    Carbon::setTestNow($this->now);
    $this->formatter = Mockery::mock(Formatter::class);
    $this->config = app(Config::class);
    $this->filename = Mockery::mock(FileName::class);
    $this->writer = new Writer($this->formatter, $this->config, $this->filename);
    $this->directory = sys_get_temp_dir().'/laravel-sql-reporter-tests/'.uniqid('', true);
    config(['sql-reporter.general.directory' => $this->directory]);
    $this->filesystem = new Filesystem;
});

afterEach(function () {
    $this->filesystem->deleteDirectory($this->directory);
});

it('creates directory if it does not exist for 1st query', function () {
    $query = SqlQuery::make(1, 'test', 5.41);
    config(['sql-reporter.queries.enabled' => false]);
    expect($this->directory)->not->toBeDirectory();
    $this->writer->writeQuery($query);
    expect($this->directory)->toBeDirectory()
        ->and($this->filesystem->allFiles($this->directory))->toBeEmpty();
});

it('does not create directory if it does not exist for 2nd query', function () {
    $query = SqlQuery::make(2, 'test', 5.41);
    config(['sql-reporter.queries.enabled' => false]);
    expect($this->directory)->not->toBeDirectory();
    $this->writer->writeQuery($query);
    expect($this->directory)->not->toBeDirectory();
});

it('creates log file', function () {
    $lineContent = 'Sample log line';
    $expectedContent = "-- header\nSample log line\n";
    $expectedFileName = $this->now->format('Y-m').'-log.sql';

    $query = SqlQuery::make(1, 'test', 5.41);
    $this->formatter->shouldReceive('getLine')->once()->with($query)->andReturn($lineContent);
    $this->formatter->shouldReceive('getHeader')->once()->withNoArgs()->andReturn('-- header');

    config(['sql-reporter.queries.include_pattern' => '#.*#i']);

    $this->filename->shouldReceive('getLogfile')->times(2)->withNoArgs()->andReturn($expectedFileName);
    $this->writer->writeQuery($query);
    expect($this->directory)->toBeDirectory()
        ->and($this->filesystem->allFiles($this->directory))->toHaveCount(1)
        ->and($this->directory.'/'.$expectedFileName)->toBeFile()
        ->and(file_get_contents($this->directory.'/'.$expectedFileName))->toBe($expectedContent);
});

it('appends to existing log file', function () {
    $expectedFileName = $this->now->format('Y-m').'-log.sql';
    mkdir($this->directory, 0777, true);
    $initialContent = "Initial file content\n";
    file_put_contents($this->directory.'/'.$expectedFileName, $initialContent);

    $lineContent = 'Sample log line';
    $expectedContent = $initialContent."-- header\nSample log line\n";

    $query = SqlQuery::make(1, 'test', 5.41);
    $this->formatter->shouldReceive('getLine')->once()->with($query)->andReturn($lineContent);
    $this->formatter->shouldReceive('getHeader')->once()->withNoArgs()->andReturn('-- header');
    config(['sql-reporter.queries.include_pattern' => '#.*#i']);
    $this->filename->shouldReceive('getLogfile')->times(2)->withNoArgs()->andReturn($expectedFileName);
    expect($this->directory.'/'.$expectedFileName)->toBeFile();
    $this->writer->writeQuery($query);
    expect($this->filesystem->allFiles($this->directory))->toHaveCount(1)
        ->and($this->directory.'/'.$expectedFileName)->toBeFile()
        ->and(file_get_contents($this->directory.'/'.$expectedFileName))->toBe($expectedContent);
});

it('replaces current file content for 1st query when overriding is turned on', function () {
    $expectedFileName = $this->now->format('Y-m').'-log.sql';
    mkdir($this->directory, 0777, true);
    $initialContent = "Initial file content\n";
    file_put_contents($this->directory.'/'.$expectedFileName, $initialContent);

    $lineContent = 'Sample log line';
    $expectedContent = "-- header\nSample log line\n";

    $query = SqlQuery::make(1, 'test', 5.41);
    $this->formatter->shouldReceive('getLine')->once()->with($query)->andReturn($lineContent);
    $this->formatter->shouldReceive('getHeader')->once()->withNoArgs()->andReturn('-- header');
    config([
        'sql-reporter.queries.include_pattern' => '#.*#i',
        'sql-reporter.queries.override_log' => true,
    ]);
    $this->filename->shouldReceive('getLogfile')->times(2)->withNoArgs()->andReturn($expectedFileName);
    expect($this->directory.'/'.$expectedFileName)->toBeFile();
    $this->writer->writeQuery($query);
    expect($this->filesystem->allFiles($this->directory))->toHaveCount(1)
        ->and($this->directory.'/'.$expectedFileName)->toBeFile()
        ->and(file_get_contents($this->directory.'/'.$expectedFileName))->toBe($expectedContent);
});

it('appends to current file content for 2nd query when overriding is turned on', function () {
    $expectedFileName = $this->now->format('Y-m').'-log.sql';
    mkdir($this->directory, 0777, true);
    $initialContent = "Initial file content\n";
    file_put_contents($this->directory.'/'.$expectedFileName, $initialContent);

    $lineContent = 'Sample log line';
    $expectedContent = "-- header\n$lineContent\n$lineContent\n";

    $query1 = SqlQuery::make(1, 'test', 5.41);
    $query2 = SqlQuery::make(2, 'test', 5.41);
    $this->formatter->shouldReceive('getLine')->twice()->andReturn($lineContent);
    $this->formatter->shouldReceive('getHeader')->once()->withNoArgs()->andReturn('-- header');
    config([
        'sql-reporter.queries.include_pattern' => '#.*#i',
        'sql-reporter.queries.override_log' => true,
    ]);
    $this->filename->shouldReceive('getLogfile')->times(3)->withNoArgs()->andReturn($expectedFileName);
    expect($this->directory.'/'.$expectedFileName)->toBeFile();
    $this->writer->writeQuery($query1);
    $this->writer->writeQuery($query2);
    expect($this->filesystem->allFiles($this->directory))->toHaveCount(1)
        ->and($this->directory.'/'.$expectedFileName)->toBeFile()
        ->and(file_get_contents($this->directory.'/'.$expectedFileName))->toBe($expectedContent);
});

it('saves select query to file when pattern set to select queries', function () {
    $expectedFileName = $this->now->format('Y-m').'-log.sql';
    $lineContent = 'Sample log line';
    $expectedContent = "\n$lineContent\n";

    $query = SqlQuery::make(1, 'select * FROM test', 5.41);
    $this->formatter->shouldReceive('getLine')->once()->with($query)->andReturn($lineContent);
    $this->formatter->shouldReceive('getHeader')->once()->withNoArgs()->andReturn('');
    config(['sql-reporter.queries.include_pattern' => '#^SELECT .*$#i']);
    $this->filename->shouldReceive('getLogfile')->twice()->withNoArgs()->andReturn($expectedFileName);
    $this->writer->writeQuery($query);
    expect($this->directory)->toBeDirectory()
        ->and($this->filesystem->allFiles($this->directory))->toHaveCount(1)
        ->and($this->directory.'/'.$expectedFileName)->toBeFile()
        ->and(file_get_contents($this->directory.'/'.$expectedFileName))->toBe($expectedContent);
});

it('doesnt save select query to file when pattern set to insert or update queries', function () {
    $query = SqlQuery::make(1, 'select * FROM test', 5.41);
    config(['sql-reporter.queries.include_pattern' => '#^(?:UPDATE|INSERT) .*$#i']);
    $this->writer->writeQuery($query);
    expect($this->directory)->toBeDirectory()
        ->and($this->filesystem->allFiles($this->directory))->toHaveCount(0);
});

it('saves insert query to file when pattern set to insert or update queries', function () {
    $expectedFileName = $this->now->format('Y-m').'-log.sql';
    $lineContent = 'Sample log line';
    $expectedContent = "\n$lineContent\n";

    $query = SqlQuery::make(1, 'INSERT INTO test(one, two, three) values(?, ?, ?)', 5.41);
    $this->formatter->shouldReceive('getLine')->once()->with($query)->andReturn($lineContent);
    $this->formatter->shouldReceive('getHeader')->once()->withNoArgs()->andReturn('');
    config(['sql-reporter.queries.include_pattern' => '#^(?:UPDATE|INSERT) .*$#i']);
    $this->filename->shouldReceive('getLogfile')->twice()->withNoArgs()->andReturn($expectedFileName);
    $this->writer->writeQuery($query);
    expect($this->directory)->toBeDirectory()
        ->and($this->filesystem->allFiles($this->directory))->toHaveCount(1)
        ->and($this->directory.'/'.$expectedFileName)->toBeFile()
        ->and(file_get_contents($this->directory.'/'.$expectedFileName))->toBe($expectedContent);
});

it('uses raw query without bindings when using query pattern', function () {
    $expectedFileName = $this->now->format('Y-m').'-log.sql';
    $lineContent = 'Sample log line';
    $expectedContent = "\n$lineContent\n";

    $query = SqlQuery::make(1, 'UPDATE test SET x = 2 WHERE id = 3', 5.41);
    $this->formatter->shouldReceive('getLine')->once()->with($query)->andReturn($lineContent);
    $this->formatter->shouldReceive('getHeader')->once()->withNoArgs()->andReturn('');
    config(['sql-reporter.queries.include_pattern' => '#^(?:UPDATE test SET x = \d |INSERT ).*$#i']);
    $this->filename->shouldReceive('getLogfile')->twice()->withNoArgs()->andReturn($expectedFileName);
    $this->writer->writeQuery($query);
    expect($this->directory)->toBeDirectory()
        ->and($this->filesystem->allFiles($this->directory))->toHaveCount(1)
        ->and($this->directory.'/'.$expectedFileName)->toBeFile()
        ->and(file_get_contents($this->directory.'/'.$expectedFileName))->toBe($expectedContent);
});

it('only logs slow queries', function () {
    $query1 = SqlQuery::make(1, 'test1', 5.41);
    $query2 = SqlQuery::make(2, 'test2', 500.5);

    config(['sql-reporter.queries.min_exec_time' => 500]);

    $this->formatter->shouldReceive('getHeader')->once()->withNoArgs()->andReturn('');
    $this->formatter->shouldReceive('getLine')->once()->with($query2)->andReturn('');

    $writer = Mockery::mock(Writer::class, [$this->formatter, $this->config, $this->filename])
        ->makePartial()->shouldAllowMockingProtectedMethods();
    $writer->shouldAllowMockingProtectedMethods();
    $writer->shouldReceive('writeLine')->twice()->andReturn(false);

    expect($writer->writeQuery($query1))->toBeFalse()
        ->and($writer->writeQuery($query2))->toBeTrue();
});

it('respects query patterns', function () {
    $query1 = SqlQuery::make(1, 'select foo from bar', 5.41);
    $query2 = SqlQuery::make(2, 'update bar set foo = 1', 3.55);
    $query3 = SqlQuery::make(3, "update bar set last_visit = '2021-06-03 10:26:00'", 3.22);

    config([
        'sql-reporter.queries.include_pattern' => '/^(?!SELECT).*$/i',
        'sql-reporter.queries.exclude_pattern' => '/^UPDATE.*last_visit/i',
    ]);

    $this->formatter->shouldReceive('getHeader')->once()->withNoArgs()->andReturn('');
    $this->formatter->shouldReceive('getLine')->once()->with($query2)->andReturn('');

    $writer = Mockery::mock(Writer::class, [$this->formatter, $this->config, $this->filename])
        ->makePartial()->shouldAllowMockingProtectedMethods();
    $writer->shouldAllowMockingProtectedMethods();
    $writer->shouldReceive('writeLine')->twice()->andReturn(false);

    expect($writer->writeQuery($query1))->toBeFalse()
        ->and($writer->writeQuery($query2))->toBeTrue()
        ->and($writer->writeQuery($query3))->toBeFalse();
});

it('respects the report pattern from the config to determine if a query should be reported', function (string $query, bool $report) {
    expect($this->writer->shouldReportSqlQuery(SqlQuery::make(1, $query, 1)))->toBe($report);
})->with([
    ['select * from users', false],
    ['delete from users', true],
]);

it('can provide a callback to the writer to determine if a query should be reported', function (string $query, bool $report) {
    config(['sql-reporter.queries.report_pattern' => null]);
    Writer::shouldReportQuery(fn (SqlQuery $query) => ! str_contains($query->rawQuery, 'sessions'));
    expect($this->writer->shouldReportSqlQuery(SqlQuery::make(1, $query, 1)))->toBe($report);
})->with([
    ['delete from sessions', false],
    ['delete from users', true],
]);

it('can combine the report pattern config and the callback to determine if a query should be reported', function (string $query, bool $report) {
    config(['sql-reporter.queries.report_pattern' => '/^DELETE.*$/i']);
    Writer::shouldReportQuery(fn (SqlQuery $query) => ! str_contains($query->rawQuery, 'sessions'));
    expect($this->writer->shouldReportSqlQuery(SqlQuery::make(1, $query, 1)))->toBe($report);
})->with([
    ['select * from users', false],
    ['delete from sessions', false],
    ['delete from users', true],
]);

it('accepts any callable to determine if a query should be reported', function ($callback) {
    // untyped, so Pest resolves the dataset closures (it passes them through as-is for a callable parameter)
    Writer::shouldReportQuery($callback);

    expect($this->writer->shouldReportSqlQuery(SqlQuery::make(1, 'delete from sessions', 1)))->toBeFalse()
        ->and($this->writer->shouldReportSqlQuery(SqlQuery::make(2, 'delete from users', 1)))->toBeTrue();
})->with([
    'closure' => fn () => fn (SqlQuery $query) => ! str_contains($query->rawQuery, 'sessions'),
    'invokable object' => fn () => new class
    {
        public function __invoke(SqlQuery $query): bool
        {
            return ! str_contains($query->rawQuery, 'sessions');
        }
    },
    'array callable' => fn () => [new class
    {
        public function check(SqlQuery $query): bool
        {
            return ! str_contains($query->rawQuery, 'sessions');
        }
    }, 'check'],
]);

it('casts the callback result to bool', function () {
    // e.g. preg_match() returns int, which caused a TypeError under strict types
    Writer::shouldReportQuery(fn (SqlQuery $query) => preg_match('/users/', $query->rawQuery));

    expect($this->writer->shouldReportSqlQuery(SqlQuery::make(1, 'delete from users', 1)))->toBeTrue()
        ->and($this->writer->shouldReportSqlQuery(SqlQuery::make(2, 'delete from sessions', 1)))->toBeFalse();
});

it('resets the report callback when passing null', function () {
    Writer::shouldReportQuery(fn () => false);
    Writer::shouldReportQuery(null);

    expect($this->writer->shouldReportSqlQuery(SqlQuery::make(1, 'delete from users', 1)))->toBeTrue();
});

it('dispatches an event when there are queries to report', function () {
    Event::fake();

    $query1 = SqlQuery::make(1, 'select * from users', 5.41);
    $query2 = SqlQuery::make(2, 'delete from users', 5.41);

    $expectedFileName = $this->now->format('Y-m').'-log.sql';
    $lineContent1 = 'Sample log line 1';
    $lineContent2 = 'Sample log line 2';

    $this->formatter->shouldReceive('getLine')->once()->with($query1)->andReturn($lineContent1);
    $this->formatter->shouldReceive('getLine')->once()->with($query2)->andReturn($lineContent2);
    $this->formatter->shouldReceive('getHeader')->once()->withNoArgs()->andReturn('-- header');
    $this->filename->shouldReceive('getLogfile')->times(3)->withNoArgs()->andReturn($expectedFileName);

    $this->writer->writeQuery($query1);
    $this->writer->writeQuery($query2);
    $this->writer->report();

    Event::assertDispatched(function (QueryLogWritten $event) use ($lineContent2) {
        return count($event->reportQueries) === 1 && $event->reportQueries[0] === $lineContent2;
    });
});

it('does not dispatch an event when there are no queries to report', function () {
    Event::fake();

    $this->writer->report();

    Event::assertNotDispatched(QueryLogWritten::class);
});
