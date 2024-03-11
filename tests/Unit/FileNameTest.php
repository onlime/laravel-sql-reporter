<?php

use Carbon\Carbon;
use Illuminate\Container\Container;
use Onlime\LaravelSqlReporter\Config;
use Onlime\LaravelSqlReporter\FileName;

beforeEach(function () {
    Carbon::setTestNow('2015-03-07 08:16:09');
    $this->app = Mockery::mock(Container::class);
    $this->config = Mockery::mock(Config::class);
    $this->filename = new FileName($this->app, $this->config);
});

it('returns valid file name for queries when not running in console', function () {
    $this->app->shouldReceive('runningInConsole')->once()->withNoArgs()
        ->andReturn(false);
    $this->config->shouldReceive('queriesFileName')->once()->withNoArgs()
        ->andReturn('sample[Y]-test-[m]-abc-[d]');
    $this->config->shouldReceive('fileExtension')->once()->withNoArgs()
        ->andReturn('.extension');
    $result = $this->filename->getLogfile();
    expect($result)->toBe('sample2015-test-03-abc-07.extension');
});

it('returns valid file name for queries when running in console', function () {
    $this->app->shouldReceive('runningInConsole')->once()->withNoArgs()
        ->andReturn(true);
    $this->config->shouldReceive('consoleSuffix')->once()->withNoArgs()
        ->andReturn('-artisan-suffix');
    $this->config->shouldReceive('queriesFileName')->once()->withNoArgs()
        ->andReturn('sample[Y]-test-[m]-abc-[d]');
    $this->config->shouldReceive('fileExtension')->once()->withNoArgs()
        ->andReturn('.extension');
    $result = $this->filename->getLogfile();
    expect($result)->toBe('sample2015-test-03-abc-07-artisan-suffix.extension');
});
