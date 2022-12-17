<?php

namespace Tests\Unit;

use Illuminate\Support\Carbon;
use Onlime\LaravelSqlReporter\SqlQuery;

class SqlQueryTest extends UnitTestCase
{
    /** @test */
    public function it_returns_valid_number()
    {
        $value = 56;
        $query = new SqlQuery($value, 'test', [], 130);
        $this->assertSame($value, $query->number());
    }

    /** @test */
    public function it_returns_valid_raw_query()
    {
        $value = 'SELECT * FROM tests WHERE a = ?';
        $query = new SqlQuery(56, $value, ['test'], 130);
        $this->assertSame($value, $query->raw());
    }

    /** @test */
    public function it_returns_valid_bindings_array()
    {
        $value = ['one', new \DateTime(), 3];
        $query = new SqlQuery(56, 'test', $value, 130);
        $this->assertSame($value, $query->bindings());
    }

    /** @test */
    public function it_returns_valid_time()
    {
        $value = 130.0;
        $query = new SqlQuery(56, 'test', [], $value);
        $this->assertSame($value, $query->time());
    }

    /** @test */
    public function it_returns_valid_query_with_replaced_bindings()
    {
        $sql = <<<'EOF'
SELECT * FROM tests WHERE a = ? AND CONCAT(?, '%'
 , ?) = ? AND column = ?
EOF;

        $bindings = ["'test", Carbon::yesterday(), new \DateTime('tomorrow'), 453, 67.23];
        $query = new SqlQuery(56, $sql, $bindings, 130);

        $expectedSql = <<<EOF
SELECT * FROM tests WHERE a = '\'test' AND CONCAT('{$bindings[1]->toDateTimeString()}', '%'
 , '{$bindings[2]->format('Y-m-d H:i:s')}') = 453 AND column = 67.23
EOF;

        $this->assertSame($expectedSql, $query->get());
    }

    /** @test */
    public function it_returns_valid_query_with_replaced_bindings_for_immutable_date()
    {
        $sql = <<<'EOF'
SELECT * FROM tests WHERE a = ? AND CONCAT(?, '%'
 , ?) = ? AND column = ?
EOF;

        $bindings = ["'test", Carbon::yesterday(), new \DateTimeImmutable('tomorrow'), 453, 67.23];
        $query = new SqlQuery(56, $sql, $bindings, 130);

        $expectedSql = <<<EOF
SELECT * FROM tests WHERE a = '\'test' AND CONCAT('{$bindings[1]->toDateTimeString()}', '%'
 , '{$bindings[2]->format('Y-m-d H:i:s')}') = 453 AND column = 67.23
EOF;

        $this->assertSame($expectedSql, $query->get());
    }

    /** @test */
    public function it_returns_valid_query_when_question_mark_in_quotes()
    {
        $sql = <<<EOF
SELECT * FROM tests WHERE a = '?' AND b = "?" AND c = ? AND D = '\\?' AND e = "\"?" AND f = ?;
EOF;
        $bindings = ["'test", 52];
        $query = new SqlQuery(56, $sql, $bindings, 130);

        $expectedSql = <<<EOF
SELECT * FROM tests WHERE a = '?' AND b = "?" AND c = '\'test' AND D = '\\?' AND e = "\"?" AND f = 52;
EOF;

        $this->assertSame($expectedSql, $query->get());
    }

    /** @test */
    public function it_returns_valid_query_for_named_bindings()
    {
        $sql = <<<'EOF'
SELECT * FROM tests WHERE a = ? AND b = :email AND c = ? AND D = :something AND true;
EOF;
        $bindings = ["'test", 52, 'example', 53, 77];
        $query = new SqlQuery(56, $sql, $bindings, 130);

        $expectedSql = <<<EOF
SELECT * FROM tests WHERE a = '\'test' AND b = 52 AND c = 'example' AND D = 53 AND true;
EOF;

        $this->assertSame($expectedSql, $query->get());
    }

    /** @test */
    public function it_returns_valid_query_for_multiple_named_bindings_in_other_order()
    {
        $sql = <<<'EOF'
SELECT * FROM tests WHERE a = :email AND b = :something AND c = :test AND true;
EOF;
        $bindings = [':test' => 'other value', ':email' => 'test@example.com', ':something' => 'test'];
        $query = new SqlQuery(56, $sql, $bindings, 130);

        $expectedSql = <<<'EOF'
SELECT * FROM tests WHERE a = 'test@example.com' AND b = 'test' AND c = 'other value' AND true;
EOF;

        $this->assertSame($expectedSql, $query->get());
    }

    /** @test */
    public function it_returns_valid_query_when_empty_string_as_column_and_date_binding()
    {
        $sql = <<<'EOF'
SELECT id, '' AS title FROM test WHERE created_at >= :from AND created_at <= :to
EOF;
        $bindings = [':from' => '2018-03-19 21:01:01', ':to' => '2018-03-19 22:01:01'];
        $query = new SqlQuery(56, $sql, $bindings, 130);

        $expectedSql = <<<'EOF'
SELECT id, '' AS title FROM test WHERE created_at >= '2018-03-19 21:01:01' AND created_at <= '2018-03-19 22:01:01'
EOF;

        $this->assertSame($expectedSql, $query->get());
    }

    /** @test */
    public function it_handles_both_colon_and_non_colon_parameters()
    {
        $sql = <<<'EOF'
SELECT * FROM tests WHERE a = :email AND b = :something;
EOF;
        // one binding name stats with colon, other without it - both should work
        $bindings = [':email' => 'test@example.com', 'something' => 'test'];
        $query = new SqlQuery(56, $sql, $bindings, 130);

        $expectedSql = <<<'EOF'
SELECT * FROM tests WHERE a = 'test@example.com' AND b = 'test';
EOF;

        $this->assertSame($expectedSql, $query->get());
    }

    /** @test */
    public function it_leaves_null_values_not_changed()
    {
        $sql = <<<'EOF'
UPDATE tests SET a = :email, b = :something WHERE id=:id;
EOF;

        $bindings = [':email' => 'test@example.com', 'something' => null, 'id' => 5];
        $query = new SqlQuery(56, $sql, $bindings, 130);

        $expectedSql = <<<'EOF'
UPDATE tests SET a = 'test@example.com', b = null WHERE id=5;
EOF;

        $this->assertSame($expectedSql, $query->get());
    }

    /** @test */
    public function it_converts_booleans_to_int()
    {
        $sql = <<<'EOF'
SELECT * FROM users WHERE archived = :archived AND active = :active;
EOF;

        $bindings = [':archived' => true, ':active' => false];
        $query = new SqlQuery(56, $sql, $bindings, 130);

        $expectedSql = <<<'EOF'
SELECT * FROM users WHERE archived = 1 AND active = 0;
EOF;

        $this->assertSame($expectedSql, $query->get());
    }

    /** @test */
    public function it_returns_valid_sql_query_object_when_bindings_are_null()
    {
        $number = 56;
        $sql = 'SELECT * FROM everywhere WHERE user = ?';
        $time = 516.32;

        $query = new SqlQuery($number, $sql, null, $time);

        $expectedSql = 'SELECT * FROM everywhere WHERE user = ?';

        $this->assertSame($number, $query->number());
        $this->assertSame($expectedSql, $query->get());
        $this->assertSame([], $query->bindings());
        $this->assertSame($time, $query->time());
    }
}
