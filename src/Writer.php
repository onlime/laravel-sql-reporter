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
     * Save queries to log.
     *
     * @param SqlQuery $query
     */
    public function save(SqlQuery $query)
    {
        $this->createDirectoryIfNotExists($query->number());

        if ($this->shouldLogQuery($query)) {
            if (0 === $this->logCount) {
                // only write header information on first query to be logged
                $this->saveLine(
                    $this->formatter->getHeader(),
                    $this->config->queriesOverrideLog()
                );
            }
            $this->saveLine(
                $this->formatter->getLine($query)
            );
            $this->logCount++;
        }
    }

    /**
     * Create directory if it does not exist yet.
     *
     * @param int $queryNumber
     */
    protected function createDirectoryIfNotExists(int $queryNumber)
    {
        if ($queryNumber == 1 && ! file_exists($directory = $this->directory())) {
            mkdir($directory, 0777, true);
        }
    }

    /**
     * Get directory where file should be located.
     *
     * @return string
     */
    protected function directory()
    {
        return rtrim($this->config->logDirectory(), '\\/');
    }

    /**
     * Verify whether query should be logged.
     *
     * @param SqlQuery $query
     *
     * @return bool
     */
    protected function shouldLogQuery(SqlQuery $query)
    {
        return $this->config->queriesEnabled() &&
            $query->time() >= $this->config->queriesMinExecTime() &&
            preg_match($this->config->queriesIncludePattern(), $query->raw()) &&
            !preg_match($this->config->queriesExcludePattern(), $query->raw());
    }

    /**
     * Save data to log file.
     *
     * @param string $line
     * @param bool $override
     */
    protected function saveLine(string $line, bool $override = false)
    {
        file_put_contents(
            $this->directory() . DIRECTORY_SEPARATOR . $this->fileName->getLogfile(),
            $line . PHP_EOL,
            $override ? 0 : FILE_APPEND
        );
    }
}
