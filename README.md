# Laravel SQL Reporter

[![Packagist](https://img.shields.io/packagist/dt/onlime/laravel-sql-reporter.svg)](https://packagist.org/packages/onlime/laravel-sql-reporter)
[![Build Status](https://github.com/onlime/laravel-sql-reporter/actions/workflows/ci.yml/badge.svg)](https://github.com/onlime/laravel-sql-reporter/actions/workflows/ci.yml)
[![Coverage Status](https://coveralls.io/repos/github/onlime/laravel-sql-reporter/badge.svg)](https://coveralls.io/github/onlime/laravel-sql-reporter)

This module allows you to log SQL queries to log file in Laravel framework. It's useful mainly
when developing your application to verify whether your queries are valid and to make sure your application doesn't run too many or too slow database queries.

You may also use this in production as it should not cause a lot of overhead. Logged queries can be limited by query pattern, and logging only occurs at the end of each request or artisan command execution.

It reports a lot of metadata like total query count, total execution time, origin (request URL/console command), authenticated user, app environment, client browser agent / IP / hostname.

## Installation

1. Run
   ```bash
   $ composer require onlime/laravel-sql-reporter --dev
   ```
   in console to install this module (Notice `--dev` flag - it's recommended to use this package only for development). 

   Laravel uses Package Auto-Discovery and it will automatically load this service provider so you don't need to add anything into the `providers` section of `config/app.php`.
    
2. Run the following in your console to publish the default configuration file:
    
    ```bash
    $ php artisan vendor:publish --provider="Onlime\LaravelSqlReporter\Providers\ServiceProvider"
    ```
    
    By default, you should not edit published file because all the settings are loaded from `.env` file by default.

3. In your `.env` file add the following entries:

    ```ini
    SQL_REPORTER_DIRECTORY="logs/sql"
    SQL_REPORTER_USE_SECONDS=false
    SQL_REPORTER_CONSOLE_SUFFIX=
    SQL_REPORTER_LOG_EXTENSION=".sql"
    SQL_REPORTER_QUERIES_ENABLED=true
    SQL_REPORTER_QUERIES_OVERRIDE_LOG=false
    SQL_REPORTER_QUERIES_PATTERN="#.*#i"
    SQL_REPORTER_QUERIES_MIN_EXEC_TIME=0
    SQL_REPORTER_QUERIES_FILE_NAME="[Y-m]-log"
    SQL_REPORTER_FORMAT_HEADER_FIELDS="origin,datetime,status,user,env,agent,ip,host,referer"
    SQL_REPORTER_FORMAT_ENTRY_FORMAT="-- Query [query_nr] [[query_time]]\\n[query]"
    ```
    
    and adjust values to your needs. You can skip variables for which you want to use default values.

    To only log DML / modifying queries like `INSERT`, `UPDATE`, `DELETE`, I recommend to use:

    ```ini
   SQL_REPORTER_QUERIES_PATTERN="/^(?!SELECT).*$/i"
   ```
    
    If you have also `.env.example` it's recommended to add those entries also in `.env.example` file just to make sure everyone knows about those env variables. Be aware that `SQL_REPORTER_DIRECTORY` is directory inside storage directory.
    
    To find out more about those setting please take a look at [Configuration file](config/sql-reporter.php)
    
4. Make sure directory specified in `.env` file exists in storage path, and you have valid permissions to create and modify files in this directory (If it does not exist this package will automatically create it when needed, but it's recommended to create it manually with valid file permissions)

5. Make sure on live server you will set logging SQL queries to false in your `.env` file: `SQL_REPORTER_QUERIES_ENABLED=false`. This package is recommended to be used only for development to not impact production application performance.

## Optional

For optional GeoIP support (adding country information to client IP in log headers), you may install [torann/geoip](https://github.com/Torann/laravel-geoip) in your project:

```bash
$ composer require torann/geoip
$ php artisan vendor:publish --provider="Torann\GeoIP\GeoIPServiceProvider"
```

It will be auto-detected, no configuration needed for this.

## Development

Checkout project and run tests:

```bash
$ git clone https://github.com/onlime/laravel-sql-reporter.git
$ cd laravel-sql-reporter
$ composer install

# run unit tests
$ vendor/bin/phpunit
# run unit tests with coverage report
$ XDEBUG_MODE=coverage vendor/bin/phpunit
```

## FAQ

### How does this package differ from `mnabialek/laravel-sql-logger` ?

This package was inspired by [mnabialek/laravel-sql-logger](https://github.com/mnabialek/laravel-sql-logger) and basically does the same thing: Logging your SQL queries. Here's the difference:

- Query logging is not triggered upon each query execution but instead at a final step, using `RequestHandled` and `CommandFinished` events.
- This allows us to include much more information about the whole query executions like total query count, total execution time, and very detailed header information like origin (request URL/console command), authenticated user, app environment, client browser agent / IP / hostname.
- This package is greatly simplified and only provides support for Laravel 8+ / PHP 8
- It uses the Laravel built-in query logging (`DB::enableQueryLog()`) which logs all queries in memory, which should perform much better than writing every single query to the log file.
- By default, `onlime/laravel-sql-reporter` produces much nicer log output, especially since we only write header information before the first query.

Sample log output:

```
-- --------------------------------------------------
-- Datetime: 2021-05-28 15:24:46
-- Origin:   (request) GET http://localhost:8000/demo
-- Status:   Executed 3 queries in 1.85ms
-- User:     
-- Env:      local
-- Agent:    Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:88.0) Gecko/20100101 Firefox/88.0
-- Ip:       127.0.0.1
-- Host:     localhost
-- Referer:  
-- --------------------------------------------------
-- Query 1 [1.45ms]
select * from `users` where `id` = 1 limit 1;
-- Query 3 [0.4ms]
update `users` set `last_visit` = '2021-05-28 15:24:46' where `id` = 1;
```

In comparison, sample log output of `mnabialek/laravel-sql-logger`:

```
/*==================================================*/
/* Origin (request): GET http://localhost:8000/mail/api/user
   Query 1 - 2021-05-20 21:00:08 [1.4ms] */
select * from `users` where `id` = 1 limit 1;
/*==================================================*/
/* Origin (request): GET http://localhost:8000/mail/api/user
   Query 2 - 2021-05-20 21:00:08 [4.72ms] */
update `users` set `last_visit` = '2021-05-20 21:00:08' where `id` = 1;
```

## Authors

Author of this awesome package is **[Philip Iezzi (Onlime GmbH)](https://www.onlime.ch/)**.

Large parts of this package were ported from the original [mnabialek/laravel-sql-logger](https://github.com/mnabialek/laravel-sql-logger). Credits go to **[Marcin Nabiałek](http://marcin.nabialek.org/en/)**.
Please star his great package on GitHub! You may use `composer thanks` for this.

## Changes

All changes are listed in [CHANGELOG](CHANGELOG.md)

## Caveats

- If your application crashes, this package will not log any queries, as logging is only triggered at the end. As alternative, you could use [mnabialek/laravel-sql-logger](https://github.com/mnabialek/laravel-sql-logger) which triggers sql logging on each query execution.
- It's currently not possible to log slow queries into a separate logfile. I wanted to keep that package simpel.

## Todo

- [ ] Improve unit testing to reach 100% coverage
- [ ] Add browser type information to log headers, using hisorange/browser-detect
- [ ] Support for Lumen

## License

This package is licenced under the [MIT license](LICENSE) however support is more than welcome.