<?php

namespace Onlime\LaravelSqlReporter\Providers;

use Illuminate\Support\Facades\DB;
use Onlime\LaravelSqlReporter\Config;
use Onlime\LaravelSqlReporter\Listeners\SqlQueryLogSubscriber;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The subscriber classes to register.
     *
     * @var array
     */
    protected $subscribe = [
        SqlQueryLogSubscriber::class,
    ];

    /**
     * @var Config
     */
    protected $config;

    /**
     * @inheritdoc
     */
    public function register()
    {
        $this->config = $this->app->make(Config::class);

        $this->mergeConfigFrom($this->configFileLocation(), 'sql-reporter');

        if ($this->config->queriesEnabled()) {
            // registering subscriber(s) from $this->subscribe
            parent::register();
        }
    }

    /**
     * @inheritdoc
     */
    public function boot()
    {
        $this->publishes([
            $this->configFileLocation() => config_path('sql-reporter.php'),
        ], 'config');

        if ($this->config->queriesEnabled()) {
            DB::enableQueryLog();
        }
    }

    /**
     * Get package config file location.
     */
    protected function configFileLocation(): string
    {
        return realpath(__DIR__ . '/../../config/sql-reporter.php');
    }
}
