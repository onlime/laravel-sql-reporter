<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stevebauman\Location\Facades\Location;

class Formatter
{
    /**
     * Replacement for the values of redacted URL parameters.
     */
    private const REDACTED = '[redacted]';

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
            'referer'  => fn () => $this->redactUrl((string) $request->header('referer')),
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
        $queryLog = DB::getQueryLog();

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
     * Get client IP, with country information appended if enabled (optional GeoIP lookup with stevebauman/location).
     */
    protected function ipLine(?string $ip): string
    {
        if ($ip === null || $ip === '127.0.0.1' || ! $this->config->geoIp() || ! class_exists(Location::class)) {
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
     * Get request line, with the values of sensitive route and query string parameters redacted.
     */
    protected function getRequestLine(): string
    {
        $request = $this->app->make('request');
        $query = $request->getQueryString();

        return $request->method().' '.$this->redactUrl(
            $request->root().'/'.ltrim($this->requestPath($request), '/').($query !== null ? '?'.$query : '')
        );
    }

    /**
     * Get the path of a request, with the values of sensitive route parameters (e.g. a password reset token) redacted.
     */
    private function requestPath(Request $request): string
    {
        $route = $request->route();
        if (! $route instanceof Route) {
            return $request->path();
        }

        $patterns = $this->redactionPatterns();
        $redacted = array_filter(
            $route->parameterNames(),
            fn (string $name): bool => $this->isRedacted($name, $patterns)
        );
        if ($redacted === []) {
            return $request->path();
        }

        // The value of a route parameter can't be reliably located in the path (it might also show up in another
        // segment, or span several segments), so build the path from the route URI, e.g. /reset-password/{token}.
        $path = preg_replace_callback('/\{(\w+)\??\}/', function (array $matches) use ($route, $redacted): string {
            $value = $route->originalParameter($matches[1]);

            return match (true) {
                $value === null => '', // a missing optional parameter
                in_array($matches[1], $redacted, true) => self::REDACTED,
                default => str_replace('%2F', '/', rawurlencode($value)),
            };
        }, $route->uri());

        return rtrim($path ?? $route->uri(), '/');
    }

    /**
     * Redact the values of sensitive query string parameters in a URL, e.g. tokens or signatures of signed URLs.
     */
    protected function redactUrl(string $url): string
    {
        if (! str_contains($url, '?')) {
            return $url;
        }

        $patterns = $this->redactionPatterns();
        [$base, $query] = explode('?', $url, 2);

        $parameters = array_map(function (string $parameter) use ($patterns): string {
            $name = Str::before($parameter, '=');

            return $name !== $parameter && $this->isRedacted($name, $patterns) ? $name.'='.self::REDACTED : $parameter;
        }, explode('&', $query));

        return $base.'?'.implode('&', $parameters);
    }

    /**
     * Get the patterns of the URL parameters to redact, lowercase for case-insensitive matching.
     *
     * @return array<int, string>
     */
    private function redactionPatterns(): array
    {
        return array_map(Str::lower(...), $this->config->redactUrlParameters());
    }

    /**
     * Whether the value of a route or query string parameter is to be redacted. A nested query string parameter like
     * filter[token] is, if any of its names matches.
     *
     * @param  array<int, string>  $patterns  lowercase, with wildcards
     */
    private function isRedacted(string $name, array $patterns): bool
    {
        $names = preg_split('/[\[\]]+/', Str::lower(urldecode($name)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($names as $part) {
            if (Str::is($patterns, $part)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get separator line.
     */
    protected function separatorLine(): string
    {
        return '-- '.str_repeat('-', 50);
    }
}
