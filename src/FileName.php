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
    ) {}

    /**
     * Create file name for query log.
     */
    public function getLogfile()
    {
        return
            $this->parseFileName($this->config->queriesFileName()) .
            $this->suffix() .
            $this->config->fileExtension();
    }

    /**
     * Get file suffix.
     *
     * @return string
     */
    protected function suffix()
    {
        return $this->app->runningInConsole() ? $this->config->consoleSuffix() : '';
    }

    /**
     * Parse file name to include date in it.
     *
     * @param string $fileName
     *
     * @return string
     */
    protected function parseFileName($fileName)
    {
        return preg_replace_callback('#(\[.*\])#U', function ($matches) {
            $format = str_replace(['[',']'], [], $matches[1]);
            return Carbon::now()->format($format);
        }, $fileName);
    }
}
