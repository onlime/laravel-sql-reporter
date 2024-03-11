<?php

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
