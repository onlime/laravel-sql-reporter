<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter\Tests\Fixtures;

use Onlime\LaravelSqlReporter\Attributes\DontLogQueries;

#[DontLogQueries]
class UnloggedJob extends InsertUserJob {}
