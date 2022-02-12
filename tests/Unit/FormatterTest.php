<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Onlime\LaravelSqlReporter\Config;
use Onlime\LaravelSqlReporter\Formatter;
use Onlime\LaravelSqlReporter\SqlQuery;
use Mockery;

class FormatterTest extends UnitTestCase
{
    /** @test */
    public function it_formats_header_in_valid_way_when_running_via_http()
    {
        $config = Mockery::mock(Config::class);
        $app = Mockery::mock(Container::class);
        $app->shouldReceive('runningInConsole')->once()->withNoArgs()->andReturn(false);
        $app->shouldReceive('environment')->once()->withNoArgs()->andReturn('testing');
        $config->shouldReceive('useSeconds')->once()->withNoArgs()->andReturn(false);
        $config->shouldReceive('headerFields')->once()->withNoArgs()
            ->andReturn(explode(',', 'origin,datetime,status,user,env,agent,ip,host,referer'));
        $request = Mockery::mock(Request::class);
        $app->shouldReceive('offsetGet')->times(2)->with('request')->andReturn($request);
        $request->shouldReceive('method')->once()->withNoArgs()->andReturn('DELETE');
        $request->shouldReceive('fullUrl')->once()->withNoArgs()
            ->andReturn('http://example.com/test');

        $now = '2015-03-04 08:12:07';
        Carbon::setTestNow($now);

        DB::shouldReceive('getQueryLog')->once()->withNoArgs()->andReturn([
            ['query' => 'foo', 'bindings' => [], 'time' => 1.23],
            ['query' => 'bar', 'bindings' => [], 'time' => 4.56],
        ]);
        Auth::shouldReceive('user')->once()->withNoArgs()->andReturn(null);
        \Illuminate\Support\Facades\Request::shouldReceive('ip')->once()->withNoArgs()->andReturn('127.0.0.1');
        \Illuminate\Support\Facades\Request::shouldReceive('userAgent')->once()->withNoArgs()->andReturn('Mozilla/5.0');
        \Illuminate\Support\Facades\Request::shouldReceive('header')->once()->with('referer')->andReturn('');

        $formatter = new Formatter($app, $config);
        $result = $formatter->getHeader();

        $expected = <<<EOT
-- --------------------------------------------------
-- Datetime: {$now}
-- Origin:   (request) DELETE http://example.com/test
-- Status:   Executed 2 queries in 5.79ms
-- User:     
-- Env:      testing
-- Agent:    Mozilla/5.0
-- Ip:       127.0.0.1
-- Host:     localhost
-- Referer:  
-- --------------------------------------------------
EOT;

        $this->assertSame($expected, $result);
    }

    /** @test */
    public function it_formats_header_in_valid_way_when_running_in_console()
    {
        $config = Mockery::mock(Config::class);
        $app = Mockery::mock(Container::class);
        $app->shouldReceive('runningInConsole')->once()->withNoArgs()->andReturn(true);
        $app->shouldReceive('environment')->once()->withNoArgs()->andReturn('testing');
        $config->shouldReceive('useSeconds')->once()->withNoArgs()->andReturn(false);
        $config->shouldReceive('headerFields')->once()->withNoArgs()
            ->andReturn(explode(',', 'origin,datetime,status,user,env,agent,ip,host,referer'));
        $request = Mockery::mock(Request::class);
        $app->shouldReceive('offsetGet')->once()->with('request')->andReturn($request);
        $request->shouldReceive('server')->once()->with('argv', [])->andReturn('php artisan test');

        $now = '2015-03-04 08:12:07';
        Carbon::setTestNow($now);

        DB::shouldReceive('getQueryLog')->once()->withNoArgs()->andReturn([]);
        Auth::shouldReceive('user')->once()->withNoArgs()->andReturn(null);
        \Illuminate\Support\Facades\Request::shouldReceive('ip')->once()->withNoArgs()->andReturn('127.0.0.1');
        \Illuminate\Support\Facades\Request::shouldReceive('userAgent')->once()->withNoArgs()->andReturn('Mozilla/5.0');
        \Illuminate\Support\Facades\Request::shouldReceive('header')->once()->with('referer')->andReturn('');

        $formatter = new Formatter($app, $config);
        $result = $formatter->getHeader();

        $expected = <<<EOT
-- --------------------------------------------------
-- Datetime: {$now}
-- Origin:   (console) php artisan test
-- Status:   Executed 0 queries in 0ms
-- User:     
-- Env:      testing
-- Agent:    Mozilla/5.0
-- Ip:       127.0.0.1
-- Host:     localhost
-- Referer:  
-- --------------------------------------------------
EOT;

        $this->assertSame($expected, $result);
    }

    /** @test */
    public function it_formats_line_in_valid_way_when_milliseconds_are_used()
    {
        $config = Mockery::mock(Config::class);
        $app = Mockery::mock(Container::class);
        $config->shouldReceive('useSeconds')->once()->withNoArgs()->andReturn(false);
        $config->shouldReceive('entryFormat')->once()->withNoArgs()
            ->andReturn('/* Query [query_nr] - [datetime] [[query_time]] */\n[query]\n[separator]\n');

        $now = '2015-03-04 08:12:07';
        Carbon::setTestNow($now);

        $query = Mockery::mock(SqlQuery::class);
        $number = 434;
        $time = 617.24;
        $sql = 'SELECT * FROM somewhere';
        $query->shouldReceive('number')->once()->withNoArgs()->andReturn($number);
        $query->shouldReceive('get')->once()->withNoArgs()->andReturn($sql);
        $query->shouldReceive('time')->once()->withNoArgs()->andReturn($time);

        $formatter = new Formatter($app, $config);
        $result = $formatter->getLine($query);

        $expected = <<<EOT
/* Query {$number} - {$now} [{$time}ms] */
{$sql};
-- --------------------------------------------------

EOT;

        $this->assertSame($expected, $result);
    }

    /** @test */
    public function it_formats_line_in_valid_way_when_custom_entry_format_was_used()
    {
        $config = Mockery::mock(Config::class);
        $app = Mockery::mock(Container::class);
        $config->shouldReceive('useSeconds')->once()->withNoArgs()->andReturn(false);
        $config->shouldReceive('entryFormat')->once()->withNoArgs()
            ->andReturn("[separator]\n[query_nr] : [datetime] [query_time]\n[query]\n[separator]\n");

        $now = '2015-03-04 08:12:07';
        Carbon::setTestNow($now);

        $query = Mockery::mock(SqlQuery::class);
        $number = 434;
        $time = 617.24;
        $sql = 'SELECT * FROM somewhere';
        $query->shouldReceive('number')->once()->withNoArgs()->andReturn($number);
        $query->shouldReceive('get')->once()->withNoArgs()->andReturn($sql);
        $query->shouldReceive('time')->once()->withNoArgs()->andReturn($time);

        $formatter = new Formatter($app, $config);
        $result = $formatter->getLine($query);

        $expected = <<<EOT
-- --------------------------------------------------
{$number} : {$now} {$time}ms
{$sql};
-- --------------------------------------------------

EOT;

        $this->assertSame($expected, $result);
    }

    /** @test */
    public function it_formats_line_in_valid_way_when_seconds_are_used()
    {
        $config = Mockery::mock(Config::class);
        $app = Mockery::mock(Container::class);
        $config->shouldReceive('useSeconds')->once()->withNoArgs()->andReturn(true);
        $config->shouldReceive('entryFormat')->once()->withNoArgs()
            ->andReturn('/* Query [query_nr] - [datetime] [[query_time]] */\n[query]\n[separator]\n');

        $now = '2015-03-04 08:12:07';
        Carbon::setTestNow($now);

        $query = Mockery::mock(SqlQuery::class);
        $number = 434;
        $time = 617.24;
        $sql = 'SELECT * FROM somewhere';
        $query->shouldReceive('number')->once()->withNoArgs()->andReturn($number);
        $query->shouldReceive('get')->once()->withNoArgs()->andReturn($sql);
        $query->shouldReceive('time')->once()->withNoArgs()->andReturn($time);

        $formatter = new Formatter($app, $config);
        $result = $formatter->getLine($query);

        $expected = <<<EOT
/* Query {$number} - {$now} [0.61724s] */
{$sql};
-- --------------------------------------------------

EOT;

        $this->assertSame($expected, $result);
    }
}
