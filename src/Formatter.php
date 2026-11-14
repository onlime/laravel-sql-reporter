<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter;

use Carbon\Carbon;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stevebauman\Location\Facades\Location;

class Formatter
{
    public function __construct(
        private Application $app,
        private Config $config
    ) {}

    /**
     * Get formatted single query line(s).
     */
    public function getLine(SqlQuery $query): string
    {
        $replace = [
            '[query_nr]'   => (string) $query->number,
            '[datetime]'   => Carbon::now()->toDateTimeString(),
            '[query_time]' => $this->time($query->time),
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

        // Try to resolve username, which is stored in 'email' field by default, but could be mapped by username() method
        // by some custom trait. data_get() also works for models that prevent accessing missing attributes.
        $username = ($user = Auth::user())
            ? (method_exists($user, 'username') ? $user->username() : data_get($user, 'email'))
            : '';

        $queryLog  = DB::getRawQueryLog();
        $times     = Arr::pluck($queryLog, 'time');
        $totalTime = $this->time(array_sum($times));
        $request   = $this->app->make('request');
        $ip        = $request->ip();

        // TODO: datetime information should be replaced by lowest query timestamp, see https://github.com/laravel/framework/pull/37514
        $data = [
            'datetime' => Carbon::now()->format($this->config->headerDatetimeFormat()),
            'origin'   => $this->originLine(),
            'status'   => sprintf('Executed %s queries in %s', count($queryLog), $totalTime),
            'user'     => $username,
            'guard'    => Auth::check() ? Auth::getDefaultDriver() : '',
            'env'      => $this->app->environment(),
            'agent'    => $request->userAgent() ?? PHP_SAPI,
            'ip'       => $ip,
            'host'     => $ip !== null ? gethostbyaddr($ip) : '',
            'referer'  => $request->header('referer'),
        ];
        $headers = Arr::only($data, $headerFields);
        if (empty($headers)) {
            // none of the configured header fields is supported
            return '';
        }

        // (optional) GeoIP lookup if stevebauman/location is installed, appending country information to IP
        if (in_array('ip', $headerFields) && $ip !== '127.0.0.1' && class_exists(Location::class)) {
            $position = Location::get();
            $headers['ip'] .= sprintf(
                ' (%s / %s)',
                $position->isoCode ?? 'XX',
                $position->countryName ?? 'Unknown Country'
            );
        }

        // get formatted header lines with padded keys
        $formatted    = [];
        $formatted[]  = $this->separatorLine();
        $maxKeyLength = max(array_map('strlen', array_keys($headers)));
        foreach ($headers as $key => $value) {
            $formatted[] = trim('-- '.Str::padRight(Str::ucfirst($key).':', $maxKeyLength + 2).$value);
        }
        $formatted[] = $this->separatorLine();

        return implode(PHP_EOL, $formatted);
    }

    /**
     * Format time.
     */
    protected function time(float $time): string
    {
        return $this->config->useSeconds() ? ($time / 1000.0).'s' : $time.'ms';
    }

    /**
     * Get origin line.
     */
    protected function originLine(): string
    {
        return $this->app->runningInConsole()
            ? '(console) '.$this->getArtisanLine()
            : '(request) '.$this->getRequestLine();
    }

    /**
     * Get query line.
     */
    protected function getQueryLine(SqlQuery $query): string
    {
        return $query->rawQuery.';';
    }

    /**
     * Get Artisan line.
     */
    protected function getArtisanLine(): string
    {
        $command = $this->app->make('request')->server('argv', []);

        return is_array($command) ? implode(' ', $command) : (string) $command;
    }

    /**
     * Get request line.
     */
    protected function getRequestLine(): string
    {
        $request = $this->app->make('request');

        return $request->method().' '.$request->fullUrl();
    }

    /**
     * Get separator line.
     */
    protected function separatorLine(): string
    {
        return '-- '.str_repeat('-', 50);
    }
}
