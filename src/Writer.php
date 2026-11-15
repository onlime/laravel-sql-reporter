<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter;

use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Onlime\LaravelSqlReporter\Events\QueryLogWritten;

class Writer
{
    /**
     * Log record counter.
     * This is only used to count queries that are actually logged.
     */
    private int $loggedQueryCount = 0;

    /**
     * Lines of the log entry (its header and queries), written at once by flush().
     *
     * @var list<string>
     */
    private array $lines = [];

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
     * Add a query to the log entry, which flush() writes to the log file.
     *
     * @return bool true if query was added to the log entry, false if skipped
     */
    public function writeQuery(SqlQuery $query): bool
    {
        if ($this->shouldLogQuery($query)) {
            if ($this->loggedQueryCount === 0) {
                // only write header information on first query to be logged
                $this->lines[] = $this->reportHeader = $this->formatter->getHeader();
            }
            $this->lines[] = $logLine = $this->formatter->getLine($query);
            if ($this->shouldReportSqlQuery($query)) {
                $this->reportQueries[] = $logLine;
            }
            $this->loggedQueryCount++;
            return true;
        }
        return false;
    }

    /**
     * Write the log entry to the log file, at once and with an exclusive lock, so the log entries of requests,
     * commands or jobs logged at the same time don't get mixed up.
     */
    public function flush(): void
    {
        if ($this->lines === []) {
            return;
        }

        $content = implode(PHP_EOL, $this->lines).PHP_EOL;
        $this->lines = [];

        // another process (e.g. a queue worker) might create the directory at the same time
        $directory = $this->config->logDirectory();
        if (! is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        file_put_contents(
            $directory.DIRECTORY_SEPARATOR.$this->logFile(),
            $content,
            LOCK_EX | ($this->config->queriesOverrideLog() ? 0 : FILE_APPEND)
        );
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
     * Get the name of the log file: the file_name option with its date formats, the console suffix (when running in
     * console) and the extension, e.g. 2026-11-log-artisan.sql for [Y-m]-log.
     */
    private function logFile(): string
    {
        return Str::of($this->config->queriesFileName())
            // date formats in brackets, e.g. [Y-m]
            ->replaceMatches('/\[(.*?)\]/', fn (array $matches) => Carbon::now()->format($matches[1]))
            ->append(App::runningInConsole() ? $this->config->consoleSuffix() : '', $this->config->fileExtension())
            ->toString();
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
