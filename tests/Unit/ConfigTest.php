<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository;
use Onlime\LaravelSqlReporter\Config;

beforeEach(function () {
    $this->repository = Mockery::mock(Repository::class);
    $this->config = new Config($this->repository);
});

it('returns valid values for queries enabled', function () {
    $this->repository->shouldReceive('get')->once()->with('sql-reporter.queries.enabled')
        ->andReturn(1);
    expect($this->config->queriesEnabled())->toBeTrue();

    $this->repository->shouldReceive('get')->once()->with('sql-reporter.queries.enabled')
        ->andReturn(0);
    expect($this->config->queriesEnabled())->toBeFalse();
});

it('returns valid value for slow log time', function () {
    $value = 700.0;
    $this->repository->shouldReceive('get')->once()->with('sql-reporter.queries.min_exec_time')
        ->andReturn($value);
    expect($this->config->queriesMinExecTime())->toBe($value);
});

it('returns valid values for queries override log', function () {
    $this->repository->shouldReceive('get')->once()->with('sql-reporter.queries.override_log')
        ->andReturn(1);
    expect($this->config->queriesOverrideLog())->toBeTrue();

    $this->repository->shouldReceive('get')->once()->with('sql-reporter.queries.override_log')
        ->andReturn(0);
    expect($this->config->queriesOverrideLog())->toBeFalse();
});

it('returns valid value for log directory', function () {
    $value = 'sample directory';
    $this->repository->shouldReceive('get')->once()->with('sql-reporter.general.directory')
        ->andReturn($value);
    expect($this->config->logDirectory())->toBe($value);
});

it('returns valid values for use seconds', function () {
    $this->repository->shouldReceive('get')->once()->with('sql-reporter.general.use_seconds')
        ->andReturn(1);
    expect($this->config->useSeconds())->toBeTrue();

    $this->repository->shouldReceive('get')->once()->with('sql-reporter.general.use_seconds')
        ->andReturn(0);
    expect($this->config->useSeconds())->toBeFalse();
});

it('returns valid values for console suffix', function () {
    $this->repository->shouldReceive('get')->once()->with('sql-reporter.general.console_log_suffix')
        ->andReturn('-artisan');
    expect($this->config->consoleSuffix())->toBe('-artisan');

    $this->repository->shouldReceive('get')->once()->with('sql-reporter.general.console_log_suffix')
        ->andReturn('');
    expect($this->config->consoleSuffix())->toBe('');
});

it('returns valid value for queries include pattern', function () {
    $value = 'sample pattern';
    $this->repository->shouldReceive('get')->once()->with('sql-reporter.queries.include_pattern')
        ->andReturn($value);
    expect($this->config->queriesIncludePattern())->toBe($value);
});

it('returns valid value for queries exclude pattern', function () {
    $value = 'sample pattern';
    $this->repository->shouldReceive('get')->once()->with('sql-reporter.queries.exclude_pattern')
        ->andReturn($value);
    expect($this->config->queriesExcludePattern())->toBe($value);
});

it('returns valid file extension', function () {
    $value = '.sql';
    $this->repository->shouldReceive('get')->once()->with('sql-reporter.general.extension')
        ->andReturn($value);
    expect($this->config->fileExtension())->toBe($value);
});

it('returns valid queries file name', function () {
    $value = '[Y-m-d]-sample';
    $this->repository->shouldReceive('get')->once()->with('sql-reporter.queries.file_name')
        ->andReturn($value);
    expect($this->config->queriesFileName())->toBe($value);
});

it('returns valid value for entry format', function () {
    $this->repository->shouldReceive('get')->once()->with('sql-reporter.formatting.entry_format')
        ->andReturn('[sample]/[example]/foo');
    expect($this->config->entryFormat())->toBe('[sample]/[example]/foo');
});

it('casts the slow log time to float', function () {
    // values from .env are strings
    $this->repository->shouldReceive('get')->once()->with('sql-reporter.queries.min_exec_time')
        ->andReturn('100');
    expect($this->config->queriesMinExecTime())->toBe(100.0);
});

it('returns valid value for queries report pattern', function () {
    $this->repository->shouldReceive('get')->once()->with('sql-reporter.queries.report_pattern')
        ->andReturn('/^DELETE/i');
    expect($this->config->queriesReportPattern())->toBe('/^DELETE/i');

    $this->repository->shouldReceive('get')->once()->with('sql-reporter.queries.report_pattern')
        ->andReturn(null);
    expect($this->config->queriesReportPattern())->toBe('');
});

it('returns valid value for header fields', function () {
    $this->repository->shouldReceive('get')->once()->with('sql-reporter.formatting.header_fields')
        ->andReturn(['datetime', 'origin']);
    expect($this->config->headerFields())->toBe(['datetime', 'origin']);
});

it('returns valid value for header datetime format', function () {
    $this->repository->shouldReceive('get')->once()->with('sql-reporter.formatting.header_datetime_format')
        ->andReturn('Y-m-d H:i:s P');
    expect($this->config->headerDatetimeFormat())->toBe('Y-m-d H:i:s P');
});

it('returns valid values for queries log jobs', function () {
    $this->repository->shouldReceive('get')->once()->with('sql-reporter.queries.log_jobs')
        ->andReturn(1);
    expect($this->config->queriesLogJobs())->toBeTrue();

    $this->repository->shouldReceive('get')->once()->with('sql-reporter.queries.log_jobs')
        ->andReturn(null);
    expect($this->config->queriesLogJobs())->toBeFalse();
});

it('returns valid values for queries exclude commands', function (mixed $value, array $expected) {
    $this->repository->shouldReceive('get')->once()->with('sql-reporter.queries.exclude_commands', [])
        ->andReturn($value);
    expect($this->config->queriesExcludeCommands())->toBe($expected);
})->with([
    'array (default)' => [['pulse:check', 'Laravel\\Horizon\\Console\\HorizonCommand'], ['pulse:check', 'Laravel\\Horizon\\Console\\HorizonCommand']],
    'comma-separated (.env)' => ['pulse:check, Laravel\\Horizon\\Console\\HorizonCommand,,', ['pulse:check', 'Laravel\\Horizon\\Console\\HorizonCommand']],
    'empty (.env)' => ['', []],
    'missing in a published config file' => [null, []],
]);

it('returns valid values for redact url parameters', function (mixed $value, array $expected) {
    // a published config file of an older version doesn't have this option yet, so it falls back to the defaults
    $this->repository->shouldReceive('get')->once()
        ->with('sql-reporter.formatting.redact_url_parameters', Config::DEFAULT_REDACT_URL_PARAMETERS)
        ->andReturn($value);
    expect($this->config->redactUrlParameters())->toBe($expected);
})->with([
    'array (default)' => [['*token*', 'code'], ['*token*', 'code']],
    'comma-separated (.env)' => ['*token*, code,,', ['*token*', 'code']],
    'empty (.env)' => ['', []],
]);

it('returns valid values for redact columns', function (mixed $value, array $expected) {
    // a published config file of an older version doesn't have this option yet, so it falls back to the defaults
    $this->repository->shouldReceive('get')->once()
        ->with('sql-reporter.queries.redact_columns', Config::DEFAULT_REDACT_COLUMNS)
        ->andReturn($value);
    expect($this->config->redactColumns())->toBe($expected);
})->with([
    'array (default)' => [['password', 'sessions.id'], ['password', 'sessions.id']],
    'comma-separated (.env)' => ['password, sessions.id,,', ['password', 'sessions.id']],
    'empty (.env)' => ['', []],
]);

it('returns valid values for geoip', function () {
    $this->repository->shouldReceive('get')->once()->with('sql-reporter.formatting.geoip')
        ->andReturn(1);
    expect($this->config->geoIp())->toBeTrue();

    $this->repository->shouldReceive('get')->once()->with('sql-reporter.formatting.geoip')
        ->andReturn(null);
    expect($this->config->geoIp())->toBeFalse();
});
