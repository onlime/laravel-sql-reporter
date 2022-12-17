<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Illuminate\Container\Container;
use Mockery;
use Onlime\LaravelSqlReporter\Config;
use Onlime\LaravelSqlReporter\FileName;

class FileNameTest extends UnitTestCase
{
    /**
     * @var Container|\Mockery\Mock
     */
    protected $app;

    /**
     * @var Config|\Mockery\Mock
     */
    protected $config;

    /**
     * @var FileName
     */
    protected $filename;

    protected function setUp(): void
    {
        Carbon::setTestNow('2015-03-07 08:16:09');
        $this->app = Mockery::mock(Container::class);
        $this->config = Mockery::mock(Config::class);
        $this->filename = new FileName($this->app, $this->config);
    }

    /** @test */
    public function it_returns_valid_file_name_for_queries_when_not_running_in_console()
    {
        $this->app->shouldReceive('runningInConsole')->once()->withNoArgs()->andReturn(false);
        $this->config->shouldReceive('queriesFileName')->once()->withNoArgs()
            ->andReturn('sample[Y]-test-[m]-abc-[d]');
        $this->config->shouldReceive('fileExtension')->once()->withNoArgs()
            ->andReturn('.extension');
        $result = $this->filename->getLogfile();
        $this->assertSame('sample2015-test-03-abc-07.extension', $result);
    }

    /** @test */
    public function it_returns_valid_file_name_for_queries_when_running_in_console()
    {
        $this->app->shouldReceive('runningInConsole')->once()->withNoArgs()->andReturn(true);
        $this->config->shouldReceive('consoleSuffix')->once()->withNoArgs()
            ->andReturn('-artisan-suffix');
        $this->config->shouldReceive('queriesFileName')->once()->withNoArgs()
            ->andReturn('sample[Y]-test-[m]-abc-[d]');
        $this->config->shouldReceive('fileExtension')->once()->withNoArgs()
            ->andReturn('.extension');
        $result = $this->filename->getLogfile();
        $this->assertSame('sample2015-test-03-abc-07-artisan-suffix.extension', $result);
    }
}
