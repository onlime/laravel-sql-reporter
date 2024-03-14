<?php

use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Onlime\LaravelSqlReporter\Config;
use Onlime\LaravelSqlReporter\Formatter;
use Onlime\LaravelSqlReporter\SqlQuery;

it('formats header in valid way when running via http', function () {
    $config = Mockery::mock(Config::class);
    $app = Mockery::mock(Container::class);
    $app->shouldReceive('runningInConsole')->once()->withNoArgs()->andReturn(false);
    $app->shouldReceive('environment')->once()->withNoArgs()->andReturn('testing');
    $config->shouldReceive('useSeconds')->once()->withNoArgs()->andReturn(false);
    $config->shouldReceive('headerFields')->once()->withNoArgs()
        ->andReturn(explode(',', 'origin,datetime,status,user,env,agent,ip,host,referer'));
    $request = Mockery::mock(Request::class);
    $app->shouldReceive('offsetGet')->times(3)->with('request')->andReturn($request);
    $request->shouldReceive('method')->once()->withNoArgs()->andReturn('DELETE');
    $request->shouldReceive('fullUrl')->once()->withNoArgs()
        ->andReturn('http://example.com/test');
    $request->shouldReceive('ip')->once()->withNoArgs()->andReturn('127.0.0.1');
    $request->shouldReceive('userAgent')->once()->withNoArgs()->andReturn('Mozilla/5.0');
    $request->shouldReceive('header')->once()->with('referer')->andReturn('');

    $now = '2015-03-04 08:12:07';
    Carbon::setTestNow($now);

    DB::shouldReceive('getRawQueryLog')->once()->withNoArgs()->andReturn([
        ['raw_query' => 'foo', 'time' => 1.23],
        ['raw_query' => 'bar', 'time' => 4.56],
    ]);
    Auth::shouldReceive('check')->once()->withNoArgs()->andReturn(false);
    Auth::shouldReceive('user')->once()->withNoArgs()->andReturn(null);

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

    expect($result)->toBe($expected);
});

it('formats header in valid way when running in console', function () {
    $config = Mockery::mock(Config::class);
    $app = Mockery::mock(Container::class);
    $app->shouldReceive('runningInConsole')->once()->withNoArgs()->andReturn(true);
    $app->shouldReceive('environment')->once()->withNoArgs()->andReturn('testing');
    $config->shouldReceive('useSeconds')->once()->withNoArgs()->andReturn(false);
    $config->shouldReceive('headerFields')->once()->withNoArgs()
        ->andReturn(explode(',', 'origin,datetime,status,user,env,agent,ip,host,referer'));
    $request = Mockery::mock(Request::class);
    $app->shouldReceive('offsetGet')->twice()->with('request')->andReturn($request);
    $request->shouldReceive('server')->once()->with('argv', [])->andReturn('php artisan test');
    $request->shouldReceive('ip')->once()->withNoArgs()->andReturn('127.0.0.1');
    $request->shouldReceive('userAgent')->once()->withNoArgs()->andReturn('Mozilla/5.0');
    $request->shouldReceive('header')->once()->with('referer')->andReturn('');

    $now = '2015-03-04 08:12:07';
    Carbon::setTestNow($now);

    DB::shouldReceive('getRawQueryLog')->once()->withNoArgs()->andReturn([]);
    Auth::shouldReceive('check')->once()->withNoArgs()->andReturn(false);
    Auth::shouldReceive('user')->once()->withNoArgs()->andReturn(null);

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

    expect($result)->toBe($expected);
});

it('formats line in valid way when milliseconds are used', function () {
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
    $query->shouldReceive('rawQuery')->once()->withNoArgs()->andReturn($sql);
    $query->shouldReceive('time')->once()->withNoArgs()->andReturn($time);

    $formatter = new Formatter($app, $config);
    $result = $formatter->getLine($query);

    $expected = <<<EOT
/* Query {$number} - {$now} [{$time}ms] */
{$sql};
-- --------------------------------------------------

EOT;

    expect($result)->toBe($expected);
});

it('formats line in valid way when custom entry format was used', function () {
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
    $query->shouldReceive('rawQuery')->once()->withNoArgs()->andReturn($sql);
    $query->shouldReceive('time')->once()->withNoArgs()->andReturn($time);

    $formatter = new Formatter($app, $config);
    $result = $formatter->getLine($query);

    $expected = <<<EOT
-- --------------------------------------------------
{$number} : {$now} {$time}ms
{$sql};
-- --------------------------------------------------

EOT;

    expect($result)->toBe($expected);
});

it('formats line in valid way when seconds are used', function () {
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
    $query->shouldReceive('rawQuery')->once()->withNoArgs()->andReturn($sql);
    $query->shouldReceive('time')->once()->withNoArgs()->andReturn($time);

    $formatter = new Formatter($app, $config);
    $result = $formatter->getLine($query);

    $expected = <<<EOT
/* Query {$number} - {$now} [0.61724s] */
{$sql};
-- --------------------------------------------------

EOT;

    expect($result)->toBe($expected);
});
