<?php

namespace Onlime\LaravelSqlReporter;

use Carbon\Carbon;
use Illuminate\Container\Container;

class FileName
{
    /**
     * FileName constructor.
     *
     * @param Container $app
     * @param Config $config
     */
    public function __construct(
        private Container $app,
        private Config $config
    ) {
    }

    /**
     * Create file name for query log.
     */
    public function getLogfile(): string
    {
        return
            $this->parseFileName($this->config->queriesFileName()) .
            $this->suffix() .
            $this->config->fileExtension();
    }

    /**
     * Get file suffix.
     */
    protected function suffix(): string
    {
        return $this->app->runningInConsole() ? $this->config->consoleSuffix() : '';
    }

    /**
     * Parse file name to include date in it.
     */
    protected function parseFileName(string $fileName): string
    {
        return preg_replace_callback('#(\[.*\])#U', function ($matches) {
            $format = str_replace(['[', ']'], [], $matches[1]);
            return Carbon::now()->format($format);
        }, $fileName);
    }
}
