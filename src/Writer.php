<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter;

use Closure;
use Onlime\LaravelSqlReporter\Events\QueryLogWritten;

class Writer
{
    /**
     * Log record counter.
     * This is only used to count queries that are actually logged.
     */
    private int $loggedQueryCount = 0;

    /**
     * Stores query log header for later processing.
     */
    private string $reportHeader = '';

    /**
     * Stores query log lines that should be reported.
     *
     * @var list<string>
     */
    private array $reportQueries = [];

    /**
     * Callback to determine whether query should be reported.
     */
    private static ?Closure $shouldReportQuery = null;

    /**
     * Callback to determine whether a query value should be redacted.
     */
    private static ?Closure $redactBinding = null;

    public function __construct(
        private readonly Formatter $formatter,
        private readonly Config $config,
        private readonly FileName $fileName
    ) {}

    /**
     * Set callback to determine whether query should be reported, or null to reset it.
     *
     * @param  (callable(SqlQuery): bool)|null  $callback
     */
    public static function shouldReportQuery(?callable $callback): void
    {
        self::$shouldReportQuery = $callback === null ? null : $callback(...);
    }

    /**
     * Set callback to determine whether a query value (binding) should be redacted, in addition to the values of the
     * columns in the redact_columns option, or null to reset it. It gets the value, its column and table, if known,
     * and the query with its ? placeholders. Null values are never redacted.
     *
     * @param  (callable(mixed $value, ?string $column, ?string $table, string $query): bool)|null  $callback
     */
    public static function redactBindingUsing(?callable $callback): void
    {
        self::$redactBinding = $callback === null ? null : $callback(...);
    }

    /**
     * Whether the callback set with redactBindingUsing() asks to redact a query value.
     *
     * @internal
     */
    public static function shouldRedactBinding(mixed $value, ?string $column, ?string $table, string $query): bool
    {
        return self::$redactBinding !== null && (bool) (self::$redactBinding)($value, $column, $table, $query);
    }

    /**
     * Set the origin shown in the log header instead of the request or console command, e.g. a queued job.
     */
    public function setOrigin(?string $origin): void
    {
        $this->formatter->setOrigin($origin);
    }

    /**
     * Write a query to log.
     *
     * @return bool true if query was written to log, false if skipped
     */
    public function writeQuery(SqlQuery $query): bool
    {
        $this->createDirectoryIfNotExists($query->number);

        if ($this->shouldLogQuery($query)) {
            if ($this->loggedQueryCount === 0) {
                // only write header information on first query to be logged
                $this->writeLine(
                    $this->reportHeader = $this->formatter->getHeader(),
                    $this->config->queriesOverrideLog(),
                );
            }
            $logLine = $this->formatter->getLine($query);
            $this->writeLine($logLine);
            if ($this->shouldReportSqlQuery($query)) {
                $this->reportQueries[] = $logLine;
            }
            $this->loggedQueryCount++;
            return true;
        }
        return false;
    }

    /**
     * Verify whether query should be reported.
     */
    public function shouldReportSqlQuery(SqlQuery $query): bool
    {
        $pattern = $this->config->queriesReportPattern();

        if ($pattern && preg_match($pattern, $query->rawQuery) !== 1) {
            return false;
        }

        return self::$shouldReportQuery === null || (self::$shouldReportQuery)($query);
    }

    /**
     * Create directory if it does not exist yet.
     *
     * @return bool true on successful directory creation
     */
    protected function createDirectoryIfNotExists(int $queryNumber): bool
    {
        if ($queryNumber === 1 && ! is_dir($directory = $this->directory())) {
            // another process (e.g. a queue worker) might create the directory at the same time
            return @mkdir($directory, 0777, true) || is_dir($directory);
        }
        return false;
    }

    /**
     * Get directory where file should be located.
     */
    protected function directory(): string
    {
        return rtrim($this->config->logDirectory(), '\\/');
    }

    /**
     * Verify whether query should be logged.
     */
    protected function shouldLogQuery(SqlQuery $query): bool
    {
        return $this->config->queriesEnabled() &&
            $query->time >= $this->config->queriesMinExecTime() &&
            preg_match($this->config->queriesIncludePattern(), $query->rawQuery) &&
            ! preg_match($this->config->queriesExcludePattern(), $query->rawQuery);
    }

    /**
     * Write data to log file.
     *
     * @return int|false the number of bytes that were written to the file, or false on failure.
     */
    protected function writeLine(string $line, bool $override = false): int|false
    {
        return file_put_contents(
            $this->directory().DIRECTORY_SEPARATOR.$this->fileName->getLogfile(),
            $line.PHP_EOL,
            $override ? 0 : FILE_APPEND
        );
    }

    /**
     * Report the log by triggering the QueryLogWritten event for further processing.
     */
    public function report(): void
    {
        if (count($this->reportQueries) > 0) {
            QueryLogWritten::dispatch(
                $this->loggedQueryCount,
                $this->reportHeader,
                $this->reportQueries,
            );
        }
    }
}
