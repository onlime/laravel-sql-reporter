<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stevebauman\Location\Facades\Location;

class Formatter
{
    /**
     * Origin shown in the header instead of the request or console command, e.g. a queued job.
     */
    private ?string $origin = null;

    public function __construct(
        private Application $app,
        private Config $config
    ) {}

    /**
     * Set the origin shown in the header instead of the request or console command, e.g. a queued job.
     */
    public function setOrigin(?string $origin): void
    {
        $this->origin = $origin;
    }

    /**
     * Get formatted single query line(s).
     */
    public function getLine(SqlQuery $query): string
    {
        // strtr() replaces all placeholders in a single pass, so placeholders or "\n" within the query stay untouched
        return strtr($this->config->entryFormat(), [
            '[query_nr]'   => (string) $query->number,
            '[datetime]'   => Carbon::now()->toDateTimeString(),
            '[query_time]' => $this->time($query->time),
            '[query]'      => $this->getQueryLine($query),
            '[separator]'  => $this->separatorLine(),
            '\n'           => PHP_EOL,
        ]);
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

        $request = $this->app->make('request');
        $ip      = $request->ip();

        // All supported header fields in output order. Only the configured ones get resolved,
        // e.g. to avoid a reverse DNS lookup of the client IP if 'host' is not configured.
        // TODO: datetime information should be replaced by lowest query timestamp, see https://github.com/laravel/framework/pull/37514
        $resolvers = [
            'datetime' => fn () => Carbon::now()->format($this->config->headerDatetimeFormat()),
            'origin'   => fn () => $this->originLine(),
            'status'   => fn () => $this->statusLine(),
            'user'     => fn () => $this->username(),
            'guard'    => fn () => Auth::check() ? Auth::getDefaultDriver() : '',
            'env'      => fn () => $this->app->environment(),
            'agent'    => fn () => $request->userAgent() ?? PHP_SAPI,
            'ip'       => fn () => $this->ipLine($ip),
            'host'     => fn () => $ip !== null ? gethostbyaddr($ip) : '',
            'referer'  => fn () => $request->header('referer'),
        ];
        $headers = array_map(
            fn (Closure $resolve) => $resolve(),
            array_intersect_key($resolvers, array_flip($headerFields))
        );
        if (empty($headers)) {
            // none of the configured header fields is supported
            return '';
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
     * Get status line with total query count and execution time.
     */
    protected function statusLine(): string
    {
        $queryLog = DB::getRawQueryLog();

        return sprintf(
            'Executed %s queries in %s',
            count($queryLog),
            $this->time(array_sum(Arr::pluck($queryLog, 'time')))
        );
    }

    /**
     * Get username of the authenticated user.
     */
    protected function username(): string
    {
        $user = Auth::user();
        if ($user === null) {
            return '';
        }

        // The username is stored in 'email' field by default, but could be mapped by username() method by some custom
        // trait. data_get() also works for models that prevent accessing missing attributes.
        return (string) (method_exists($user, 'username') ? $user->username() : data_get($user, 'email'));
    }

    /**
     * Get client IP, with country information appended if stevebauman/location is installed (optional GeoIP lookup).
     */
    protected function ipLine(?string $ip): string
    {
        if ($ip === null || $ip === '127.0.0.1' || ! class_exists(Location::class)) {
            return (string) $ip;
        }

        $position = Location::get($ip);

        return sprintf(
            '%s (%s / %s)',
            $ip,
            $position->isoCode ?? 'XX',
            $position->countryName ?? 'Unknown Country'
        );
    }

    /**
     * Get origin line.
     */
    protected function originLine(): string
    {
        if ($this->origin !== null) {
            return $this->origin;
        }

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
