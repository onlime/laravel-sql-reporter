<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter\Providers;

use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Onlime\LaravelSqlReporter\Config;
use Onlime\LaravelSqlReporter\Listeners\LogJobQueries;
use Onlime\LaravelSqlReporter\Listeners\LogSqlQuery;
use Onlime\LaravelSqlReporter\Support\AttributeResolver;
use Onlime\LaravelSqlReporter\Support\ConsoleState;

class SqlReporterServiceProvider extends ServiceProvider
{
    protected Config $config;

    /**
     * {@inheritdoc}
     */
    public function register(): void
    {
        $this->config = $this->app->make(Config::class);

        $this->mergeConfigFrom($this->configFileLocation(), 'sql-reporter');

        $this->app->singleton(AttributeResolver::class);
        $this->app->singleton(ConsoleState::class);

        if ($this->config->queriesEnabled()) {
            // CommandFinished only provides the command name, so keep the Artisan application to look up the command
            Artisan::starting(fn (Artisan $artisan) => $this->app->make(AttributeResolver::class)->setArtisan($artisan));

            Event::listen([CommandStarting::class, CommandFinished::class, RequestHandled::class], LogSqlQuery::class);
            Event::listen([Looping::class, JobProcessing::class, JobAttempted::class, WorkerStopping::class], LogJobQueries::class);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function boot(): void
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
        return dirname(__DIR__, 2).'/config/sql-reporter.php';
    }
}
