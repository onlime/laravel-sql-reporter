<?php

namespace Tests;

use Illuminate\Contracts\Config\Repository;
use Onlime\LaravelSqlReporter\Config;
use Mockery;

class ConfigTest extends UnitTestCase
{
    /**
     * @var Repository|\Mockery\Mock
     */
    protected $repository;

    /**
     * @var Config|\Mockery\Mock
     */
    protected $config;

    protected function setUp(): void
    {
        $this->repository = Mockery::mock(Repository::class);
        $this->config = new Config($this->repository);
    }

    /** @test */
    public function it_returns_valid_values_for_queriesEnabled()
    {
        $this->repository->shouldReceive('get')->once()->with('sql-reporter.queries.enabled')
            ->andReturn(1);
        $this->assertTrue($this->config->queriesEnabled());

        $this->repository->shouldReceive('get')->once()->with('sql-reporter.queries.enabled')
            ->andReturn(0);
        $this->assertFalse($this->config->queriesEnabled());
    }

    /** @test */
    public function it_returns_valid_value_for_slowLogTime()
    {
        $value = 700.0;
        $this->repository->shouldReceive('get')->once()->with('sql-reporter.queries.min_exec_time')
            ->andReturn($value);
        $this->assertSame($value, $this->config->queriesMinExecTime());
    }

    /** @test */
    public function it_returns_valid_values_for_queriesOverrideLog()
    {
        $this->repository->shouldReceive('get')->once()->with('sql-reporter.queries.override_log')
            ->andReturn(1);
        $this->assertTrue($this->config->queriesOverrideLog());

        $this->repository->shouldReceive('get')->once()->with('sql-reporter.queries.override_log')
            ->andReturn(0);
        $this->assertFalse($this->config->queriesOverrideLog());
    }

    /** @test */
    public function it_returns_valid_value_for_logDirectory()
    {
        $value = 'sample directory';
        $this->repository->shouldReceive('get')->once()->with('sql-reporter.general.directory')
            ->andReturn($value);
        $this->assertSame($value, $this->config->logDirectory());
    }

    /** @test */
    public function it_returns_valid_values_for_useSeconds()
    {
        $this->repository->shouldReceive('get')->once()->with('sql-reporter.general.use_seconds')
            ->andReturn(1);
        $this->assertTrue($this->config->useSeconds());

        $this->repository->shouldReceive('get')->once()->with('sql-reporter.general.use_seconds')
            ->andReturn(0);
        $this->assertFalse($this->config->useSeconds());
    }

    /** @test */
    public function it_returns_valid_values_for_consoleSuffix()
    {
        $this->repository->shouldReceive('get')->once()->with('sql-reporter.general.console_log_suffix')
            ->andReturn('-artisan');
        $this->assertSame('-artisan', $this->config->consoleSuffix());

        $this->repository->shouldReceive('get')->once()->with('sql-reporter.general.console_log_suffix')
            ->andReturn('');
        $this->assertSame('', $this->config->consoleSuffix());
    }

    /** @test */
    public function it_returns_valid_value_for_queriesPattern()
    {
        $value = 'sample pattern';
        $this->repository->shouldReceive('get')->once()->with('sql-reporter.queries.pattern')
            ->andReturn($value);
        $this->assertSame($value, $this->config->queriesPattern());
    }

    /** @test */
    public function it_returns_valid_file_extension()
    {
        $value = '.sql';
        $this->repository->shouldReceive('get')->once()->with('sql-reporter.general.extension')
            ->andReturn($value);
        $this->assertSame($value, $this->config->fileExtension());
    }

    /** @test */
    public function it_returns_valid_queries_file_name()
    {
        $value = '[Y-m-d]-sample';
        $this->repository->shouldReceive('get')->once()->with('sql-reporter.queries.file_name')
            ->andReturn($value);
        $this->assertSame($value, $this->config->queriesFileName());
    }

    /** @test */
    public function it_returns_valid_value_for_entryFormat()
    {
        $this->repository->shouldReceive('get')->once()->with('sql-reporter.formatting.entry_format')
            ->andReturn('[sample]/[example]/foo');
        $this->assertSame('[sample]/[example]/foo', $this->config->entryFormat());
    }
}
