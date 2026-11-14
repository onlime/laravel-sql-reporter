<?php

declare(strict_types=1);

use Onlime\LaravelSqlReporter\Tests\TestCase;
use Onlime\LaravelSqlReporter\Tests\UnitTestCase;

pest()->extend(TestCase::class)->in('Feature');
pest()->extend(UnitTestCase::class)->in('Unit');
