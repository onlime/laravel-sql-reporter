<?php

namespace Onlime\LaravelSqlReporter;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

class Config
{
    /**
     * Config constructor.
     *
     * @param ConfigRepository $repository
     */
    public function __construct(protected ConfigRepository $repository) {}

    /**
     * Get directory where log files should be saved.
     *
     * @return string
     */
    public function logDirectory(): string
    {
        return $this->repository->get('sql-reporter.general.directory');
    }

    /**
     * Whether query execution time should be converted to seconds.
     *
     * @return bool
     */
    public function useSeconds(): bool
    {
        return (bool) $this->repository->get('sql-reporter.general.use_seconds');
    }

    /**
     * Get suffix for console logs.
     *
     * @return string
     */
    public function consoleSuffix(): string
    {
        return (string) $this->repository->get('sql-reporter.general.console_log_suffix');
    }

    /**
     * Get file extension for logs.
     *
     * @return string
     */
    public function fileExtension(): string
    {
        return $this->repository->get('sql-reporter.general.extension');
    }

    /**
     * Whether all queries should be logged.
     *
     * @return bool
     */
    public function queriesEnabled(): bool
    {
        return (bool) $this->repository->get('sql-reporter.queries.enabled');
    }

    /**
     * Minimum execution time (in milliseconds) for queries to be logged.
     *
     * @return float
     */
    public function queriesMinExecTime(): float
    {
        return $this->repository->get('sql-reporter.queries.min_exec_time');
    }

    /**
     * Whether SQL log should be overridden for each request.
     *
     * @return bool
     */
    public function queriesOverrideLog(): bool
    {
        return (bool) $this->repository->get('sql-reporter.queries.override_log');
    }

    /**
     * Get pattern for all queries.
     *
     * @return string
     */
    public function queriesPattern(): string
    {
        return $this->repository->get('sql-reporter.queries.pattern');
    }

    /**
     * Get file name (without extension) for all queries.
     *
     * @return string
     */
    public function queriesFileName(): string
    {
        return $this->repository->get('sql-reporter.queries.file_name');
    }

    /**
     * Get header fields that should be printed in header before query loglines.
     *
     * @return array
     */
    public function headerFields(): array
    {
        return $this->repository->get('sql-reporter.formatting.header_fields');
    }

    /**
     * Get query format that should be used to save query.
     *
     * @return string
     */
    public function entryFormat(): string
    {
        return $this->repository->get('sql-reporter.formatting.entry_format');
    }
}
