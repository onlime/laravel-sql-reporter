<?php

namespace Onlime\LaravelSqlReporter\Providers;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Onlime\LaravelSqlReporter\Config;
use Onlime\LaravelSqlReporter\Listeners\LogSqlQuery;

class SqlReporterServiceProvider extends ServiceProvider
{
    protected Config $config;

    /**
     * {@inheritdoc}
     */
    public function register()
    {
        $this->config = $this->app->make(Config::class);

        $this->mergeConfigFrom($this->configFileLocation(), 'sql-reporter');

        if ($this->config->queriesEnabled()) {
            Event::listen([CommandFinished::class, RequestHandled::class], LogSqlQuery::class);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function boot()
    {
        $this->publishes([
            $this->configFileLocation() => config_path('sql-reporter.php'),
        ], 'sql-reporter');

        if ($this->config->queriesEnabled()) {
            DB::enableQueryLog();
        }
    }

    /**
     * Get package config file location.
     */
    protected function configFileLocation(): string
    {
        return realpath(__DIR__.'/../../config/sql-reporter.php');
    }
}
