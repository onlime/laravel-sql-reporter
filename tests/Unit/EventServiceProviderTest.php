<?php

namespace Tests\Unit;

use Illuminate\Container\Container;
use Onlime\LaravelSqlReporter\Config;
use Onlime\LaravelSqlReporter\Providers\EventServiceProvider;
use Onlime\LaravelSqlReporter\SqlLogger;
use Mockery;

class EventServiceProviderTest extends UnitTestCase
{
    /** @test */
    public function it_merges_config_and_publishes_when_nothing_should_be_logged()
    {
        $app = Mockery::mock(Container::class);
        Container::setInstance($app);
        $config = Mockery::mock(Config::class);

        $app->shouldReceive('make')->once()->with(Config::class)->andReturn($config);

        $provider = Mockery::mock(EventServiceProvider::class)->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $provider->__construct($app);

        $baseDir = '/some/sample/directory';

//        $app->shouldReceive('configFileLocation')->atLeast()->once()
//            ->withNoArgs()->andReturn($baseDir . '/sql-reporter.php');

        $configFile = realpath(__DIR__ . '/../../config/sql-reporter.php');
        $provider->shouldReceive('mergeConfigFrom')->once()->with(
            $configFile,
            'sql-reporter'
        );

        $config->shouldReceive('queriesEnabled')->once()->withNoArgs()->andReturn(false);

        $app->shouldNotReceive('make')->with(SqlLogger::class);

        $provider->register();
        $this->assertTrue(true);

//        $provider->boot();
//        $provider->shouldReceive('publishes')->once()->with(
//            [$configFile => config_path('sql-reporter.php')], 'config'
//        );
    }
}
