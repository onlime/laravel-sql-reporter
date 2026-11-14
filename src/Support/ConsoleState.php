<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter\Support;

/**
 * State of the current console process: the nesting of Artisan commands, and whether it is a queue worker.
 */
class ConsoleState
{
    /**
     * Number of currently running Artisan commands, which are nested if a command calls Artisan::call().
     */
    private int $commandDepth = 0;

    /**
     * Whether this process is a queue worker.
     */
    private bool $queueWorker = false;

    /**
     * Register a starting command, and return whether it is the outermost command.
     */
    public function commandStarting(): bool
    {
        return $this->commandDepth++ === 0;
    }

    /**
     * Register a finished command, and return whether it was the outermost command.
     */
    public function commandFinished(): bool
    {
        $this->commandDepth = max(0, $this->commandDepth - 1);

        return $this->commandDepth === 0;
    }

    /**
     * Mark this process as a queue worker.
     */
    public function markQueueWorker(): void
    {
        $this->queueWorker = true;
    }

    /**
     * Whether this process is a queue worker.
     */
    public function isQueueWorker(): bool
    {
        return $this->queueWorker;
    }
}
