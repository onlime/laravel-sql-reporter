<?php

namespace Onlime\LaravelSqlReporter;

use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use Onlime\LaravelSqlReporter\Concerns\ReplacesBindings;

class Formatter
{
    use ReplacesBindings;

    /**
     * Formatter constructor.
     *
     * @param Container $app
     * @param Config $config
     */
    public function __construct(
        private Container $app,
        private Config $config
    ) {}

    /**
     * Get formatted single query line(s).
     */
    public function getLine(SqlQuery $query): string
    {
        $replace = [
            '[query_nr]'   => $query->number(),
            '[datetime]'   => Carbon::now()->toDateTimeString(),
            '[query_time]' => $this->time($query->time()),
            '[query]'      => $this->getQueryLine($query),
            '[separator]'  => $this->separatorLine(),
            '\n'           => PHP_EOL,
        ];
        return str_replace(array_keys($replace), array_values($replace), $this->config->entryFormat());
    }

    /**
     * Get formatted header lines.
     */
    public function getHeader(): string
    {
        $headerFields = $this->config->headerFields();
        if (empty($headerFields)) {
            return '';
        }

        $queryLog  = DB::getQueryLog();
        $times     = Arr::pluck($queryLog, 'time');
        $totalTime = $this->time(array_sum($times));
        $ip        = Request::ip();

        // TODO: datetime information should be replaced by lowest query timestamp, see https://github.com/laravel/framework/pull/37514
        $data = [
            'datetime' => Carbon::now()->toDateTimeString(),
            'origin'   => $this->originLine(),
            'status'   => sprintf('Executed %s queries in %s', count($queryLog), $totalTime),
            'user'     => Auth::user()?->username(),
            'env'      => $this->app->environment(),
            'agent'    => Request::userAgent() ?? PHP_SAPI,
            'ip'       => $ip,
            'host'     => gethostbyaddr($ip),
            'referer'  => Request::header('referer')
        ];
        $headers = Arr::only($data, $headerFields);

        // (optional) GeoIP lookup if torann/geoip is installed, appending country information to IP
        if (in_array('ip', $headerFields) && $ip !== '127.0.0.1' && function_exists('geoip')) {
            $geoip = geoip($ip);
            $headers['ip'] .= sprintf(' (%s / %s)', $geoip->iso_code, $geoip->country);
        }

        // get formatted header lines with padded keys
        $formatted    = [];
        $formatted[]  = $this->separatorLine();
        $maxKeyLength = max(array_map('strlen', array_keys($headers)));
        foreach ($headers as $key => $value) {
            $formatted[] = '-- ' . Str::padRight(Str::ucfirst($key) . ':', $maxKeyLength + 2) . $value;
        }
        $formatted[] = $this->separatorLine();

        return implode(PHP_EOL, $formatted);
    }

    /**
     * Format time.
     */
    protected function time(float $time): string
    {
        return $this->config->useSeconds() ? ($time / 1000.0) . 's' : $time . 'ms';
    }

    /**
     * Get origin line.
     */
    protected function originLine(): string
    {
        return $this->app->runningInConsole()
                ? '(console) ' . $this->getArtisanLine()
                : '(request) ' . $this->getRequestLine();
    }

    /**
     * Get query line.
     */
    protected function getQueryLine(SqlQuery $query): string
    {
        return $query->get() . ';';
    }

    /**
     * Get Artisan line.
     */
    protected function getArtisanLine(): string
    {
        $command = $this->app['request']->server('argv', []);

        if (is_array($command)) {
            $command = implode(' ', $command);
        }

        return $command;
    }

    /**
     * Get request line.
     */
    protected function getRequestLine(): string
    {
        return $this->app['request']->method() . ' ' . $this->app['request']->fullUrl();
    }

    /**
     * Get separator line.
     */
    protected function separatorLine(): string
    {
        return '-- ' . str_repeat('-', 50);
    }
}
