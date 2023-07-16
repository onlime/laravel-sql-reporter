<?php

namespace Tests\Unit;

use Onlime\LaravelSqlReporter\SqlQuery;

class SqlQueryTest extends UnitTestCase
{
    /** @test */
    public function it_returns_valid_number()
    {
        $value = 56;
        $query = new SqlQuery($value, 'test', 130);
        $this->assertSame($value, $query->number());
    }

    /** @test */
    public function it_returns_valid_raw_query()
    {
        $value = "SELECT * FROM tests WHERE a = 'test'";
        $query = new SqlQuery(56, $value, 130);
        $this->assertSame($value, $query->rawQuery());
    }

    /** @test */
    public function it_returns_valid_time()
    {
        $value = 130.0;
        $query = new SqlQuery(56, 'test', $value);
        $this->assertSame($value, $query->time());
    }
}
