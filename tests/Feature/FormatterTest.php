<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Onlime\LaravelSqlReporter\Config;
use Onlime\LaravelSqlReporter\Formatter;
use Onlime\LaravelSqlReporter\SqlQuery;
use Stevebauman\Location\Facades\Location;
use Stevebauman\Location\LocationServiceProvider;
use Stevebauman\Location\Position;

it('formats header in valid way when running via http', function () {
    $config = Mockery::mock(Config::class);
    $app = Mockery::mock(Application::class);
    $app->shouldReceive('runningInConsole')->once()->withNoArgs()->andReturn(false);
    $app->shouldReceive('environment')->once()->withNoArgs()->andReturn('testing');
    $config->shouldReceive('useSeconds')->once()->withNoArgs()->andReturn(false);
    $config->shouldReceive('headerFields')->once()->withNoArgs()
        ->andReturn(explode(',', 'datetime,origin,status,user,env,agent,ip,host,referer'));
    $config->shouldReceive('headerDatetimeFormat')->once()->withNoArgs()->andReturn('Y-m-d H:i:s P');
    $request = Mockery::mock(Request::class);
    $app->shouldReceive('make')->twice()->with('request')->andReturn($request);
    $request->shouldReceive('method')->once()->withNoArgs()->andReturn('DELETE');
    $request->shouldReceive('fullUrl')->once()->withNoArgs()
        ->andReturn('http://example.com/test');
    $request->shouldReceive('ip')->once()->withNoArgs()->andReturn('127.0.0.1');
    $request->shouldReceive('userAgent')->once()->withNoArgs()->andReturn('Mozilla/5.0');
    $request->shouldReceive('header')->once()->with('referer')->andReturn('');

    $now = '2015-03-04 08:12:07 +00:00';
    Carbon::setTestNow($now);

    DB::shouldReceive('getQueryLog')->once()->withNoArgs()->andReturn([
        ['query' => 'foo', 'bindings' => [], 'time' => 1.23],
        ['query' => 'bar', 'bindings' => [], 'time' => 4.56],
    ]);
    Auth::shouldReceive('check')->never(); // guard is not configured, so it doesn't get resolved
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
    $app = Mockery::mock(Application::class);
    $app->shouldReceive('runningInConsole')->once()->withNoArgs()->andReturn(true);
    $app->shouldReceive('environment')->once()->withNoArgs()->andReturn('testing');
    $config->shouldReceive('useSeconds')->once()->withNoArgs()->andReturn(false);
    $config->shouldReceive('headerFields')->once()->withNoArgs()
        ->andReturn(explode(',', 'datetime,origin,status,user,env,agent,ip,host,referer'));
    $config->shouldReceive('headerDatetimeFormat')->once()->withNoArgs()->andReturn('Y-m-d H:i:s P');
    $request = Mockery::mock(Request::class);
    $app->shouldReceive('make')->twice()->with('request')->andReturn($request);
    $request->shouldReceive('server')->once()->with('argv', [])->andReturn('php artisan test');
    $request->shouldReceive('ip')->once()->withNoArgs()->andReturn('127.0.0.1');
    $request->shouldReceive('userAgent')->once()->withNoArgs()->andReturn('Mozilla/5.0');
    $request->shouldReceive('header')->once()->with('referer')->andReturn('');

    $now = '2015-03-04 08:12:07 +00:00';
    Carbon::setTestNow($now);

    DB::shouldReceive('getQueryLog')->once()->withNoArgs()->andReturn([]);
    Auth::shouldReceive('check')->never(); // guard is not configured, so it doesn't get resolved
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
    $app = Mockery::mock(Application::class);
    $config->shouldReceive('useSeconds')->once()->withNoArgs()->andReturn(false);
    $config->shouldReceive('entryFormat')->once()->withNoArgs()
        ->andReturn('/* Query [query_nr] - [datetime] [[query_time]] */\n[query]\n[separator]\n');

    $now = '2015-03-04 08:12:07';
    Carbon::setTestNow($now);

    $number = 434;
    $time = 617.24;
    $sql = 'SELECT * FROM somewhere';
    $query = SqlQuery::make($number, $sql, $time);

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
    $app = Mockery::mock(Application::class);
    $config->shouldReceive('useSeconds')->once()->withNoArgs()->andReturn(false);
    $config->shouldReceive('entryFormat')->once()->withNoArgs()
        ->andReturn("[separator]\n[query_nr] : [datetime] [query_time]\n[query]\n[separator]\n");

    $now = '2015-03-04 08:12:07';
    Carbon::setTestNow($now);

    $number = 434;
    $time = 617.24;
    $sql = 'SELECT * FROM somewhere';
    $query = SqlQuery::make($number, $sql, $time);

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
    $app = Mockery::mock(Application::class);
    $config->shouldReceive('useSeconds')->once()->withNoArgs()->andReturn(true);
    $config->shouldReceive('entryFormat')->once()->withNoArgs()
        ->andReturn('/* Query [query_nr] - [datetime] [[query_time]] */\n[query]\n[separator]\n');

    $now = '2015-03-04 08:12:07';
    Carbon::setTestNow($now);

    $number = 434;
    $time = 617.24;
    $sql = 'SELECT * FROM somewhere';
    $query = SqlQuery::make($number, $sql, $time);

    $formatter = new Formatter($app, $config);
    $result = $formatter->getLine($query);

    $expected = <<<EOT
/* Query {$number} - {$now} [0.61724s] */
{$sql};
-- --------------------------------------------------

EOT;

    expect($result)->toBe($expected);
});

it('returns an empty header if none of the configured header fields is supported', function () {
    config(['sql-reporter.formatting.header_fields' => ['foo', 'bar']]);

    expect(app(Formatter::class)->getHeader())->toBe('');
});

it('leaves the host empty if the client IP is unknown', function () {
    config(['sql-reporter.formatting.header_fields' => ['ip', 'host']]);
    $this->app->instance('request', Request::create('/', server: ['REMOTE_ADDR' => null]));

    expect(app(Formatter::class)->getHeader())->toContain("-- Ip:\n-- Host:\n");
});

it('does not replace placeholders within the query', function () {
    $config = Mockery::mock(Config::class);
    $config->shouldReceive('useSeconds')->once()->withNoArgs()->andReturn(false);
    // single-quoted, so \n is a literal backslash-n, as when set in .env
    $config->shouldReceive('entryFormat')->once()->withNoArgs()->andReturn('-- Query [query_nr]\n[query]');

    // e.g. a JSON value with an escaped line break
    $sql = 'insert into `posts` (`body`) values (\'{"text":"Hello\nWorld [separator] [query_nr]"}\')';

    $formatter = new Formatter(Mockery::mock(Application::class), $config);

    expect($formatter->getLine(SqlQuery::make(7, $sql, 1.0)))->toBe('-- Query 7'.PHP_EOL.$sql.';');
});

it('includes the authenticated user and guard', function (GenericUser $user, string $expectedUser) {
    config(['sql-reporter.formatting.header_fields' => ['user', 'guard']]);
    $this->actingAs($user);

    expect(app(Formatter::class)->getHeader())->toContain("-- User:  {$expectedUser}\n-- Guard: web\n");
})->with([
    'email' => [new GenericUser(['id' => 1, 'email' => 'alice@example.com']), 'alice@example.com'],
    'username() method' => [new class(['id' => 1, 'email' => 'alice@example.com']) extends GenericUser
    {
        public function username(): string
        {
            return 'alice';
        }
    }, 'alice'],
]);

it('leaves the user empty for a model without email attribute that prevents accessing missing attributes', function () {
    config(['sql-reporter.formatting.header_fields' => ['user']]);
    Model::preventAccessingMissingAttributes();

    try {
        $user = (new User)->forceFill(['id' => 1]);
        $user->exists = true;
        expect(fn () => $user->email)->toThrow(MissingAttributeException::class);

        $this->actingAs($user);

        expect(app(Formatter::class)->getHeader())->toContain("-- User:\n");
    } finally {
        Model::preventAccessingMissingAttributes(false);
    }
});

it('appends the country to the client IP if stevebauman/location is installed', function (string $ip, string $expected) {
    $this->app->register(LocationServiceProvider::class);
    Location::fake([
        '93.184.216.34' => Position::make(['isoCode' => 'US', 'countryName' => 'United States']),
    ]);
    config(['sql-reporter.formatting.header_fields' => ['ip']]);
    $this->app->instance('request', Request::create('/', server: ['REMOTE_ADDR' => $ip]));

    expect(app(Formatter::class)->getHeader())->toContain("-- Ip: {$expected}\n");
})->with([
    'known location' => ['93.184.216.34', '93.184.216.34 (US / United States)'],
    'unknown location' => ['198.51.100.7', '198.51.100.7 (XX / Unknown Country)'],
    'localhost is not looked up' => ['127.0.0.1', '127.0.0.1'],
]);

it('formats the total execution time in seconds', function () {
    config([
        'sql-reporter.general.use_seconds' => true,
        'sql-reporter.formatting.header_fields' => ['status'],
    ]);
    DB::shouldReceive('getQueryLog')->once()->withNoArgs()->andReturn([
        ['query' => 'foo', 'bindings' => [], 'time' => 1.5],
        ['query' => 'bar', 'bindings' => [], 'time' => 2.5],
    ]);

    expect(app(Formatter::class)->getHeader())->toContain("-- Status: Executed 2 queries in 0.004s\n");
});

it('returns an empty header if no header fields are configured', function () {
    config(['sql-reporter.formatting.header_fields' => []]);

    expect(app(Formatter::class)->getHeader())->toBe('');
});
