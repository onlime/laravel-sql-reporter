<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

class Config
{
    /**
     * Default route and query string parameters to redact in URLs of the log header, also used if the option is missing
     * in a published config file of an older version.
     *
     * @var list<string>
     */
    public const DEFAULT_REDACT_URL_PARAMETERS = [
        '*token*', '*password*', '*secret*', '*signature*', '*key', 'code', 'state', 'sig',
    ];

    public function __construct(
        protected ConfigRepository $repository
    ) {}

    /**
     * Get directory where log files should be saved.
     */
    public function logDirectory(): string
    {
        return $this->repository->get('sql-reporter.general.directory');
    }

    /**
     * Whether query execution time should be converted to seconds.
     */
    public function useSeconds(): bool
    {
        return (bool) $this->repository->get('sql-reporter.general.use_seconds');
    }

    /**
     * Get suffix for console logs.
     */
    public function consoleSuffix(): string
    {
        return (string) $this->repository->get('sql-reporter.general.console_log_suffix');
    }

    /**
     * Get file extension for logs.
     */
    public function fileExtension(): string
    {
        return $this->repository->get('sql-reporter.general.extension');
    }

    /**
     * Whether all queries should be logged.
     */
    public function queriesEnabled(): bool
    {
        return (bool) $this->repository->get('sql-reporter.queries.enabled');
    }

    /**
     * Whether queries of queued jobs should be logged.
     */
    public function queriesLogJobs(): bool
    {
        return (bool) $this->repository->get('sql-reporter.queries.log_jobs');
    }

    /**
     * Get the Artisan commands (names or class names, with wildcards) whose queries should not be logged.
     *
     * @return array<int, string>
     */
    public function queriesExcludeCommands(): array
    {
        // a published config file of an older version doesn't have this option yet
        return $this->list($this->repository->get('sql-reporter.queries.exclude_commands', []));
    }

    /**
     * Minimum execution time (in milliseconds) for queries to be logged.
     */
    public function queriesMinExecTime(): float
    {
        // values from .env are strings
        return (float) $this->repository->get('sql-reporter.queries.min_exec_time');
    }

    /**
     * Whether SQL log should be overridden for each request.
     */
    public function queriesOverrideLog(): bool
    {
        return (bool) $this->repository->get('sql-reporter.queries.override_log');
    }

    /**
     * Get include pattern for queries.
     */
    public function queriesIncludePattern(): string
    {
        return $this->repository->get('sql-reporter.queries.include_pattern');
    }

    /**
     * Get exclude pattern for queries.
     */
    public function queriesExcludePattern(): string
    {
        return $this->repository->get('sql-reporter.queries.exclude_pattern');
    }

    /**
     * Get report pattern for queries.
     */
    public function queriesReportPattern(): string
    {
        return $this->repository->get('sql-reporter.queries.report_pattern') ?: '';
    }

    /**
     * Get file name (without extension) for all queries.
     */
    public function queriesFileName(): string
    {
        return $this->repository->get('sql-reporter.queries.file_name');
    }

    /**
     * Get header fields that should be printed in header before query loglines.
     *
     * @return array<int, string>
     */
    public function headerFields(): array
    {
        return $this->repository->get('sql-reporter.formatting.header_fields');
    }

    /**
     * Get header datetime format.
     */
    public function headerDatetimeFormat(): string
    {
        return $this->repository->get('sql-reporter.formatting.header_datetime_format');
    }

    /**
     * Get query format that should be used to save query.
     */
    public function entryFormat(): string
    {
        return $this->repository->get('sql-reporter.formatting.entry_format');
    }

    /**
     * Get the route and query string parameters (with wildcards) to redact in URLs of the log header.
     *
     * @return array<int, string>
     */
    public function redactUrlParameters(): array
    {
        // Unlike other options, a published config file of an older version doesn't disable this one
        return $this->list($this->repository->get(
            'sql-reporter.formatting.redact_url_parameters',
            self::DEFAULT_REDACT_URL_PARAMETERS
        ));
    }

    /**
     * Whether to append the country to the client IP, using stevebauman/location.
     */
    public function geoIp(): bool
    {
        return (bool) $this->repository->get('sql-reporter.formatting.geoip');
    }

    /**
     * Get the values of a list option, which are comma-separated if set in .env.
     *
     * @return array<int, string>
     */
    private function list(mixed $values): array
    {
        if (is_string($values)) {
            $values = array_filter(array_map(trim(...), explode(',', $values)));
        }

        return array_values((array) $values);
    }
}
