<?php

namespace Tests;

use Carbon\Carbon;
use Mockery;
use Onlime\LaravelSqlReporter\Providers\SqlReporterServiceProvider;
use Orchestra\Testbench\TestCase;

class FeatureTestCase extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        return [SqlReporterServiceProvider::class];
    }
}
