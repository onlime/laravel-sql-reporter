<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter\Tests;

use Onlime\LaravelSqlReporter\Providers\SqlReporterServiceProvider;
use Onlime\LaravelSqlReporter\Writer;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Base class for feature tests, booting a Laravel application with the package service provider.
 */
abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            SqlReporterServiceProvider::class,
        ];
    }

    protected function tearDown(): void
    {
        // reset the static report callback, so it doesn't leak into other tests
        Writer::shouldReportQuery(null);

        parent::tearDown();
    }
}
