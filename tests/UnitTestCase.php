<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter\Tests;

use Carbon\Carbon;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base class for unit tests, without booting a Laravel application.
 */
abstract class UnitTestCase extends BaseTestCase
{
    use MockeryPHPUnitIntegration;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
