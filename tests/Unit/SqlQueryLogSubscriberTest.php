<?php

namespace Tests\Unit;

use Mockery;
use Onlime\LaravelSqlReporter\SqlLogger;

class SqlQueryLogSubscriberTest extends UnitTestCase
{
    /**
     * @var SqlLogger
     */
    private $logger;

    protected function setUp(): void
    {
        $this->logger = Mockery::mock(SqlLogger::class);
    }

//    /** @test */
//    public function foo_bar()
//    {
//        // TODO
//    }
}
