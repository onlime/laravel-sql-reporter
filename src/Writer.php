<?php

namespace Onlime\LaravelSqlReporter;

class Writer
{
    /**
     * Log record counter.
     * This is only used to count queries that are actually logged.
     *
     * @var int
     */
    private int $logCount = 0;

    /**
     * Writer constructor.
     *
     * @param Formatter $formatter
     * @param Config $config
     * @param FileName $fileName
     */
    public function __construct(
        private Formatter $formatter,
        private Config $config,
        private FileName $fileName
    ) {}

    /**
     * Write a query to log.
     *
     * @param SqlQuery $query
     * @return bool true if query was written to log, false if skipped
     */
    public function writeQuery(SqlQuery $query): bool
    {
        $this->createDirectoryIfNotExists($query->number());

        if ($this->shouldLogQuery($query)) {
            if (0 === $this->logCount) {
                // only write header information on first query to be logged
                $this->writeLine(
                    $this->formatter->getHeader(),
                    $this->config->queriesOverrideLog()
                );
            }
            $this->writeLine(
                $this->formatter->getLine($query)
            );
            $this->logCount++;
            return true;
        }
        return false;
    }

    /**
     * Create directory if it does not exist yet.
     *
     * @param int $queryNumber
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
     *
     * @return string
     */
    protected function directory(): string
    {
        return rtrim($this->config->logDirectory(), '\\/');
    }

    /**
     * Verify whether query should be logged.
     *
     * @param SqlQuery $query
     * @return bool
     */
    protected function shouldLogQuery(SqlQuery $query): bool
    {
        return $this->config->queriesEnabled() &&
            $query->time() >= $this->config->queriesMinExecTime() &&
            preg_match($this->config->queriesIncludePattern(), $query->raw()) &&
            !preg_match($this->config->queriesExcludePattern(), $query->raw());
    }

    /**
     * Write data to log file.
     *
     * @param string $line
     * @param bool $override
     * @return int|false the number of bytes that were written to the file, or false on failure.
     */
    protected function writeLine(string $line, bool $override = false): int|false
    {
        return file_put_contents(
            $this->directory() . DIRECTORY_SEPARATOR . $this->fileName->getLogfile(),
            $line . PHP_EOL,
            $override ? 0 : FILE_APPEND
        );
    }
}
