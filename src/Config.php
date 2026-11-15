<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

class Config
{
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
        $commands = $this->repository->get('sql-reporter.queries.exclude_commands', []);

        // comma-separated if set in .env
        if (is_string($commands)) {
            $commands = array_filter(array_map(trim(...), explode(',', $commands)));
        }

        return array_values((array) $commands);
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
}
