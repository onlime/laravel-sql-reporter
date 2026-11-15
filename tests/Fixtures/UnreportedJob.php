<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter\Tests\Fixtures;

use Onlime\LaravelSqlReporter\Attributes\DontReportQueries;

#[DontReportQueries]
class UnreportedJob extends InsertUserJob {}
