<?php

namespace Onlime\LaravelSqlReporter;

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
     */
    private array $reportQueries = [];

    public function __construct(
        private Formatter $formatter,
        private Config $config,
        private FileName $fileName
    ) {
    }

    /**
     * Write a query to log.
     *
     * @return bool true if query was written to log, false if skipped
     */
    public function writeQuery(SqlQuery $query): bool
    {
        $this->createDirectoryIfNotExists($query->number());

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
    private function shouldReportSqlQuery(SqlQuery $query): bool
    {
        return preg_match($this->config->queriesReportPattern(), $query->rawQuery()) === 1;
    }

    /**
     * Create directory if it does not exist yet.
     *
     * @return bool true on successful directory creation
     */
    protected function createDirectoryIfNotExists(int $queryNumber): bool
    {
        if ($queryNumber == 1 && ! file_exists($directory = $this->directory())) {
            return mkdir($directory, 0777, true);
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
            $query->time() >= $this->config->queriesMinExecTime() &&
            preg_match($this->config->queriesIncludePattern(), $query->rawQuery()) &&
            ! preg_match($this->config->queriesExcludePattern(), $query->rawQuery());
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
