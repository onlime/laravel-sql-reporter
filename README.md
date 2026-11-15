# Laravel SQL Reporter

[![Latest Version on Packagist](https://img.shields.io/packagist/v/onlime/laravel-sql-reporter.svg)](https://packagist.org/packages/onlime/laravel-sql-reporter)
[![Packagist Downloads](https://img.shields.io/packagist/dt/onlime/laravel-sql-reporter.svg)](https://packagist.org/packages/onlime/laravel-sql-reporter)
[![PHP from Packagist](https://img.shields.io/packagist/php-v/onlime/laravel-sql-reporter.svg)](https://packagist.org/packages/onlime/laravel-sql-reporter)
[![Build Status](https://github.com/onlime/laravel-sql-reporter/actions/workflows/ci.yml/badge.svg)](https://github.com/onlime/laravel-sql-reporter/actions/workflows/ci.yml)
[![GitHub License](https://img.shields.io/github/license/onlime/laravel-sql-reporter.svg)](https://github.com/onlime/laravel-sql-reporter/blob/main/LICENSE)

This module allows you to log SQL queries to log file in Laravel framework. It's useful mainly
when developing your application to verify whether your queries are valid and to make sure your application doesn't run too many or too slow database queries.

You may also use this in production as it should not cause a lot of overhead, but be aware of the [sensitive data](#sensitive-data) in the log. Logged queries can be limited by query pattern, and logging only occurs at the end of each request, Artisan command, or (optionally) queued job, not per query execution.

It reports a lot of metadata like total query count, total execution time, origin (request URL and route/console command/queued job), authenticated user, app environment, client browser agent / IP / referer.

## Requirements

- PHP 8.3+ (tested up to PHP 8.6)
- Laravel 12 or 13

## Installation

1. Run
   ```bash
   $ composer require onlime/laravel-sql-reporter --dev
   ```
   in console to install this module (Notice `--dev` flag - it's recommended to use this package only for development).

   Laravel uses package auto-discovery, and it will automatically load this service provider, so you don't need to add anything into the `providers` section of `config/app.php`.

2. Run the following in your console to publish the default configuration file:

    ```bash
    $ php artisan vendor:publish --tag=sql-reporter
    ```

    By default, you should not edit published file because all the settings are loaded from `.env` file by default. When upgrading to a new version, see [Upgrading](#upgrading).

3. In your `.env` file add the following entries:

    ```ini
    SQL_REPORTER_DIRECTORY="logs/sql"
    SQL_REPORTER_USE_SECONDS=false
    SQL_REPORTER_CONSOLE_SUFFIX=
    SQL_REPORTER_LOG_EXTENSION=".sql"
    SQL_REPORTER_QUERIES_ENABLED=true
    SQL_REPORTER_QUERIES_LOG_JOBS=false
    SQL_REPORTER_QUERIES_EXCLUDE_COMMANDS='Illuminate\Console\Scheduling\ScheduleWorkCommand,Laravel\Horizon\Console\HorizonCommand,Laravel\Horizon\Console\SupervisorCommand,Laravel\Pulse\Commands\CheckCommand,Laravel\Pulse\Commands\WorkCommand,Laravel\Reverb\Servers\Reverb\Console\Commands\StartServer'
    SQL_REPORTER_QUERIES_OVERRIDE_LOG=false
    SQL_REPORTER_QUERIES_INCLUDE_PATTERN="/.*/i"
    SQL_REPORTER_QUERIES_EXCLUDE_PATTERN="/^\$/"
    SQL_REPORTER_QUERIES_REPORT_PATTERN='/^(?!select\s|start transaction|commit|(?:insert(?: (?:or )?ignore)? into|update|delete from) (?:[`"]?\w+[`"]?\.)?[`"]?(?:sessions|cache|cache_locks|jobs|job_batches|failed_jobs|password_reset_tokens|bans|logins)\b)/i'
    SQL_REPORTER_QUERIES_REDACT_COLUMNS='*password,*token,*secret*,two_factor_*,payload,sessions.id,cache.value'
    SQL_REPORTER_QUERIES_MIN_EXEC_TIME=0
    SQL_REPORTER_QUERIES_FILE_NAME="[Y-m]-log"
    SQL_REPORTER_FORMAT_HEADER_FIELDS="datetime,origin,route,status,user,guard,env,agent,ip,referer"
    SQL_REPORTER_FORMAT_HEADER_DATETIME_FORMAT="Y-m-d H:i:s P"
    SQL_REPORTER_FORMAT_ENTRY_FORMAT="-- Query [query_nr] [[query_time]]\\n[query]"
    SQL_REPORTER_FORMAT_REDACT_URL_PARAMETERS='*token*,*password*,*secret*,*signature*,*key,code,state,sig'
    SQL_REPORTER_FORMAT_GEOIP=false
    ```

    and adjust values to your needs. You can skip variables for which you want to use default values.

    To only log DML / modifying queries like `INSERT`, `UPDATE`, `DELETE`, but not logging any updates on
    `last_visit` or `remember_token`, I recommend to use:

    ```ini
    SQL_REPORTER_QUERIES_INCLUDE_PATTERN="/^(?!SELECT).*/i"
    SQL_REPORTER_QUERIES_EXCLUDE_PATTERN="/^UPDATE.*(last_visit|remember_token)/i"
    ```

    If you have also `.env.example` it's recommended to add those entries also in `.env.example` file just to make sure everyone knows about those env variables. Be aware that `SQL_REPORTER_DIRECTORY` is directory inside storage directory.

    To find out more about those setting please take a look at [Configuration file](config/sql-reporter.php)

4. Make sure directory specified in `.env` file exists in storage path, and you have valid permissions to create and modify files in this directory (If it does not exist this package will automatically create it when needed, but it's recommended to create it manually with valid file permissions)

5. Make sure on live server you will set logging SQL queries to false in your `.env` file: `SQL_REPORTER_QUERIES_ENABLED=false`. This package is recommended to be used only for development to not impact production application performance.

## Queued jobs

A queue worker (`queue:work`, `horizon:work`) is a long-running Artisan command, processing many jobs before it stops. This package handles its query log as follows, no matter how `SQL_REPORTER_QUERIES_LOG_JOBS` is set:

- The query log of a queue worker is flushed before and after each job, and while the worker is waiting for jobs. So the query log no longer grows with the number of processed jobs. The worker command itself (`Illuminate\Queue\Console\WorkCommand` or a subclass, like Horizon's `horizon:work`) is never logged, also not for `queue:work --once` or the workers started by `queue:listen`.
- Jobs on a `sync` or `deferred` queue don't run in a worker, but within the request or Artisan command that dispatched them. Their queries are logged as part of that request or command. Jobs on a `background` queue run in a separate Artisan process, which is logged like any other Artisan command.

### `SQL_REPORTER_QUERIES_LOG_JOBS`

Queries of queued jobs are not logged by default. Enable it in your `.env` file:

```ini
SQL_REPORTER_QUERIES_LOG_JOBS=true
```

The queries of each processed job are then logged separately, right after the job has been attempted, with their own header. The origin shows the job's display name, which is the class name of the job, queued event listener, notification or mailable:

```
-- --------------------------------------------------
-- Datetime: 2026-11-14 21:30:12 +01:00
-- Origin:   (job) App\Jobs\SyncInvoices
-- Route:
-- Status:   Executed 2 queries in 1.37ms
-- User:
-- Guard:
-- Env:      production
-- Agent:    Symfony
-- Ip:       127.0.0.1
-- Referer:
-- --------------------------------------------------
-- Query 1 [0.89ms]
select * from `invoices` where `synced_at` is null;
-- Query 2 [0.48ms]
update `invoices` set `synced_at` = '2026-11-14 21:30:12' where `id` in (17, 18);
```

All the other options apply to jobs as well, e.g. the include/exclude patterns and `SQL_REPORTER_QUERIES_MIN_EXEC_TIME`. Jobs run in console, so they are written to the same log file as Artisan commands, including `SQL_REPORTER_CONSOLE_SUFFIX`. Each job also dispatches its own [`QueryLogWritten` event](#querylogwritten-event) if it ran any queries to report.

- Artisan commands the job calls with `Artisan::call()` are logged as part of the job.
- With the `database` queue driver, the log of a job also contains the queries deleting (or releasing) the job in the `jobs` table, because they run while the job is processed. The default report pattern doesn't report them.

Before enabling it, be aware of these impacts:

- **Logging time per job:** Jobs are logged synchronously by the worker, right after each job attempt, not deferred like requests. This adds the time to resolve the header fields and to write the log file to every job, slowing down the processing of your queue a bit. If you enabled the `host` header field, it is the reverse DNS lookup of `127.0.0.1` in a worker, so you may remove it from `SQL_REPORTER_FORMAT_HEADER_FIELDS` for busy queues.
- **Log file size:** Every job running queries adds a log entry with a full header. On a busy queue, the log file grows much faster than with requests only. Use a daily file name like `SQL_REPORTER_QUERIES_FILE_NAME="[Y-m-d]-log"`, a separate console log file (`SQL_REPORTER_CONSOLE_SUFFIX`), logrotate, or limit the logged queries with `SQL_REPORTER_QUERIES_INCLUDE_PATTERN` / `SQL_REPORTER_QUERIES_EXCLUDE_PATTERN`.
- **`QueryLogWritten` event per job:** Your listeners run for every job that ran queries matching the report pattern, inside the worker. If they send notifications (e.g. a report by email), you may get many more of them than before. Exclude noisy jobs with the [`#[DontReportQueries]` attribute](#excluding-artisan-commands-and-queued-jobs), `SQL_REPORTER_QUERIES_REPORT_PATTERN` or `Writer::shouldReportQuery()`.
- **Feedback loops:** If your `QueryLogWritten` listener queues a job (e.g. a queued listener or notification) which runs queries to report itself, each of these jobs dispatches another `QueryLogWritten` event, and so on. Make sure such jobs are not reported, e.g. with `#[DontReportQueries]` on the queued listener or notification class.
- **Retries:** Each job attempt is logged separately, so a retried job shows up several times. Failed attempts are logged as well, with the queries up to the exception, and so are timed out jobs, right before the worker kills them.

The query log of a single job is still kept in memory until the job has finished, like for a request or an Artisan command. Jobs running a huge number of queries (e.g. imports) therefore still need the memory for all of them, no matter if `SQL_REPORTER_QUERIES_LOG_JOBS` is enabled.

## Excluding Artisan commands and queued jobs

The include, exclude and report patterns filter single queries. To exclude a whole Artisan command or queued job, add one of these attributes to its class:

```php
use Illuminate\Console\Command;
use Onlime\LaravelSqlReporter\Attributes\DontLogQueries;
use Onlime\LaravelSqlReporter\Attributes\DontReportQueries;

#[DontLogQueries]
class SyncLastLogins extends Command
{
    // runs every minute, so its queries would only clutter the log file
}

#[DontReportQueries]
class PruneStaleRecords extends Command
{
    // its deletes are expected, so they should not show up in your query reports
}
```

| Attribute | Written to the log file | `QueryLogWritten` event |
|---|---|---|
| none | yes | yes, if there are queries to report |
| `#[DontReportQueries]` | yes | no |
| `#[DontLogQueries]` | no | no |

- **`#[DontLogQueries]`**: The queries are neither written to the log file nor reported. Use it for frequent or noisy commands and jobs whose queries you never need to see, and for long-running commands: for an Artisan command, the query log is even disabled while it runs, so its queries are not collected in memory.
- **`#[DontReportQueries]`**: The queries are logged as usual, but no [`QueryLogWritten` event](#querylogwritten-event) is dispatched. Use it for commands and jobs whose modifying queries are expected, so they don't trigger your reports or notifications. This is easier to maintain than checking the `-- Origin:` header line in your `QueryLogWritten` listener, and keeps working if a command is renamed.

The attributes work on:

- Artisan command classes.
- Queued job classes, queued event listeners, queued notifications and queued mailables, if [`SQL_REPORTER_QUERIES_LOG_JOBS`](#sql_reporter_queries_log_jobs) is enabled. Add the attribute to the listener, notification or mailable class itself. Without `SQL_REPORTER_QUERIES_LOG_JOBS`, jobs are not logged anyway.
- Subclasses: an attribute on a parent class (e.g. a base command or job class) applies to all its subclasses.

Good to know:

- The attributes apply to the request, command or job that is logged as a whole. A command called with `Artisan::call()` during a request, by another command or by a queued job, or a job dispatched on a `sync` or `deferred` queue, is logged as part of that request, command or job. Its own attributes are ignored, the ones of the outermost command or job apply.
- Closure commands (`Artisan::command()` in `routes/console.php`) can't carry attributes. Use a command class if you need to exclude one.
- The query log of an excluded job is still flushed after the job, so excluded jobs don't make the queue worker's memory grow.

### `SQL_REPORTER_QUERIES_EXCLUDE_COMMANDS`

You can't add attributes to the commands of other packages. Use the `SQL_REPORTER_QUERIES_EXCLUDE_COMMANDS` option instead, which has the same effect as `#[DontLogQueries]`: the command's queries are not logged, and its query log is disabled while it runs.

This is especially important for long-running commands (daemons). They would otherwise collect every query in memory for their whole runtime, and write all of them as one huge log entry (and `QueryLogWritten` event) when they stop. Queue workers don't need to be listed, they are [handled separately](#queued-jobs).

By default, these long-running commands are excluded:

| Command | Class |
|---|---|
| `schedule:work` | `Illuminate\Console\Scheduling\ScheduleWorkCommand` |
| `horizon` | `Laravel\Horizon\Console\HorizonCommand` |
| `horizon:supervisor` | `Laravel\Horizon\Console\SupervisorCommand` |
| `pulse:check` | `Laravel\Pulse\Commands\CheckCommand` |
| `pulse:work` | `Laravel\Pulse\Commands\WorkCommand` |
| `reverb:start` | `Laravel\Reverb\Servers\Reverb\Console\Commands\StartServer` |

The option is a comma-separated list of:

- **Command names**, e.g. `pulse:check`, with `*` wildcards, e.g. `pulse:*`.
- **Fully-qualified class names**, e.g. `Laravel\Pulse\Commands\CheckCommand`, with `*` wildcards, e.g. `Laravel\Pulse\Commands\*`. A class name also matches its subclasses. Class names are more precise than command names, and keep working if a command is renamed.

Setting the option replaces the defaults, so include them if you want to keep them:

```ini
SQL_REPORTER_QUERIES_EXCLUDE_COMMANDS='Illuminate\Console\Scheduling\ScheduleWorkCommand,Laravel\Horizon\Console\HorizonCommand,Laravel\Horizon\Console\SupervisorCommand,Laravel\Pulse\Commands\*,Laravel\Reverb\Servers\Reverb\Console\Commands\StartServer,app:sync-last-logins'
```

> [!WARNING]
> Put class names in **single quotes** in your `.env` file. In double quotes, a backslash starts an escape sequence, and phpdotenv fails to parse the whole `.env` file (`Encountered an unexpected escape sequence`). Unquoted values work as well.

To exclude no command at all, set it to an empty value: `SQL_REPORTER_QUERIES_EXCLUDE_COMMANDS=`.

## Sensitive data

The log contains the values of all logged queries, e.g. personal data. Protect the log files accordingly, and keep this in mind for your `QueryLogWritten` reports, e.g. if you send them by email. Limit what is logged with `SQL_REPORTER_QUERIES_EXCLUDE_PATTERN`, and what is reported with `SQL_REPORTER_QUERIES_REPORT_PATTERN` or `Writer::shouldReportQuery()`.

### Query values

Secrets among the values of the queries are replaced by `[redacted]`, in the log file and in the `QueryLogWritten` event:

```sql
insert into `users` (`name`, `password`, `remember_token`) values ('Alice', '$2y$[redacted]', '[redacted]');
update `sessions` set `payload` = '[redacted]', `user_id` = 1 where `id` = '[redacted]';
```

- **Columns:** The values of the columns in `SQL_REPORTER_QUERIES_REDACT_COLUMNS`, which are column names (e.g. `password`) or `table.column` (e.g. `sessions.id`), case-insensitive, with `*` wildcards. The table prefix is ignored. By default: passwords (e.g. `password` or `smtp_password`), tokens (e.g. `remember_token`), secrets, Fortify's two-factor columns, the payloads of the `database` session and queue drivers, session IDs, and the values of the `database` cache store. Setting the option replaces the defaults, so include them if you want to keep them:

  ```ini
  SQL_REPORTER_QUERIES_REDACT_COLUMNS='*password,*token,*secret*,two_factor_*,payload,sessions.id,cache.value,users.iban'
  ```

  To redact no columns at all, set it to an empty value: `SQL_REPORTER_QUERIES_REDACT_COLUMNS=`.
- **Password hashes:** Always redacted except for their algorithm identifier, in any column, e.g. `'$2y$[redacted]'` (bcrypt) or `'$argon2id$[redacted]'`. This works for any hash in the modular crypt format (`$<identifier>$…`), also for legacy ones like `'$6$[redacted]'` (SHA-512-crypt) or `'$P$[redacted]'` (phpass).
- **Your own rules:** A callback, e.g. in your `AppServiceProvider`. It gets each value (except `null`), its column and table, if known, and the query with its `?` placeholders:

  ```php
  use Onlime\LaravelSqlReporter\Writer;

  Writer::redactBindingUsing(function (mixed $value, ?string $column, ?string $table, string $query) {
      // e.g. redact email addresses in any column
      return is_string($value) && str_contains($value, '@');
  });
  ```

The column of a value is found in the query, e.g. `"email" = ?`, `set "email" = ?`, `"id" in (?, ?)` or the columns of an insert. It's unknown for a value in an expression, e.g. `lower("email") = ?`, or among the values of an insert with expressions, e.g. `now()`. Such a value is only redacted if it's a password hash, or if your callback asks for it.

The include, exclude and report patterns match the redacted queries. A `Writer::shouldReportQuery()` callback still gets the original values in `SqlQuery::$bindings`.

### URLs in the log header

The log header keeps secrets in URLs out of the log. In the origin of a request, the values of sensitive route parameters and query string parameters are replaced by `[redacted]`, e.g. for a password reset link of the route `/reset-password/{token}`:

```
-- Origin:   (request) GET https://example.com/reset-password/[redacted]?email=alice%40example.com&signature=[redacted]
```

All other parameters are logged as they are, e.g. `GET https://example.com/users/42/edit`. In the referer, only the query string parameters are redacted, as its route is unknown.

The parameters to redact match the names of route parameters (e.g. `{token}`) and query string parameters, case-insensitive, with `*` wildcards. A nested query string parameter like `filter[token]` matches `token` as well. Setting the option replaces the defaults, so include them if you want to keep them:

```ini
# defaults: tokens, passwords, secrets, signatures (signed URLs), keys, OAuth authorization codes
SQL_REPORTER_FORMAT_REDACT_URL_PARAMETERS='*token*,*password*,*secret*,*signature*,*key,code,state,sig,invitation'
```

To redact no parameters at all, set it to an empty value: `SQL_REPORTER_FORMAT_REDACT_URL_PARAMETERS=`.

The `host` header field (reverse DNS lookup of the client IP) and the [GeoIP lookup](#geoip-support) are not enabled by default, as they may send the client IP to DNS servers or a third-party service, and wait for the answer.

## Optional

### GeoIP support

For optional GeoIP support (adding country information to client IP in log headers), you may install [stevebauman/location](https://github.com/stevebauman/location) in your project:

```bash
$ composer require stevebauman/location
$ php artisan vendor:publish --provider="Stevebauman\Location\LocationServiceProvider"
```

and enable it in your `.env` file:

```ini
SQL_REPORTER_FORMAT_GEOIP=true
```

Every logged request then looks up the country of the client IP. The default driver [IpApi](https://ip-api.com/) sends the client IP to ip-api.com over plain HTTP, so you may prefer a driver with a local database, e.g. `MaxMind`. Make sure you correctly configure it according to the docs: [Available Drivers](https://github.com/stevebauman/location#available-drivers)

### `QueryLogWritten` event

This package fires a `QueryLogWritten` event after the log file has been written for a request, an Artisan command or (with `SQL_REPORTER_QUERIES_LOG_JOBS` enabled) a queued job. You may use this event to further debug or analyze the logged queries in your application. It provides the number of logged queries (`$loggedQueryCount`), the formatted header (`$reportHeader`) and the formatted queries to report (`$reportQueries`).

The queries to report are filtered by the `SQL_REPORTER_QUERIES_REPORT_PATTERN` setting, which comes with a sensible default to exclude `SELECT` queries, and the modifications of Laravel's framework tables (`sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens`) and of `bans` and `logins`. If you use a table prefix (`DB_PREFIX`), add it to the table names in the pattern. If you don't want to filter any queries, you may leave this setting empty. The event is only fired if there are queries to report. To exclude whole Artisan commands or queued jobs from reporting, use the [`#[DontReportQueries]` attribute](#excluding-artisan-commands-and-queued-jobs).

In addition to the pattern, you may also configure a callback to define your own custom filtering logic, for example, in your `AppServiceProvider`:

```php
use Onlime\LaravelSqlReporter\SqlQuery;
use Onlime\LaravelSqlReporter\Writer;

Writer::shouldReportQuery(function (SqlQuery $query) {
    // Only include queries in the `QueryLogWritten` event that took longer than 100ms
    return $query->time > 100;
});
```

With the `SqlQuery` object, you have access to both `$rawQuery` and the (unprepared) `$query`/`$bindings`. The filter possibilities by providing a callback to `Writer::shouldReportQuery()` are endless! It accepts any callable, e.g. an invokable class, and `Writer::shouldReportQuery(null)` removes the callback again.

## Testing

We're using [Pest](https://pestphp.com/) for testing, [Larastan](https://github.com/larastan/larastan) for static analysis, and [Pint](https://laravel.com/docs/pint) for code style:

```bash
$ git clone https://github.com/onlime/laravel-sql-reporter.git
$ cd laravel-sql-reporter
$ composer install

$ composer test           # run the test suite
$ composer test-coverage  # run the test suite with coverage report (requires pcov or Xdebug)
$ composer analyse        # run static analysis
$ composer lint           # check code style (use `composer format` to fix it)
```

## FAQ

### How does this package differ from `mnabialek/laravel-sql-logger` ?

This package was inspired by [mnabialek/laravel-sql-logger](https://github.com/mnabialek/laravel-sql-logger) and basically does the same thing: Logging your SQL queries. Here's the difference:

- Query logging is not triggered upon each query execution but instead at a final step, using `RequestHandled` and `CommandFinished` events (and optionally per queued job).
- This allows us to include much more information about the whole query executions like total query count, total execution time, and very detailed header information like origin (request URL and route/console command/queued job), authenticated user, app environment, client browser agent / IP / referer.
- This package is greatly simplified and only provides support for Laravel 12+ / PHP 8.3+
- It uses the Laravel built-in query logging (`DB::enableQueryLog()`) which logs all queries in memory, which should perform much better than writing every single query to the log file.
- By default, `onlime/laravel-sql-reporter` produces much nicer log output, especially since we only write header information before the first query.

Sample log output:

```
-- --------------------------------------------------
-- Datetime: 2024-03-11 09:33:22 +01:00
-- Origin:   (request) GET http://localhost:8000/demo
-- Route:    demo
-- Status:   Executed 3 queries in 1.85ms
-- User:     testuser@example.com
-- Guard:    web
-- Env:      local
-- Agent:    Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:123.0) Gecko/20100101 Firefox/123.0
-- Ip:       127.0.0.1
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

## Upgrading

Most settings come from your `.env` file. But if you published the config file (`config/sql-reporter.php`), it doesn't get the new options and defaults of a new version. Either publish it again, and re-apply your own changes to it:

```bash
$ php artisan vendor:publish --tag=sql-reporter --force
```

or apply the changes listed below by hand, for each version you're upgrading across. Only the versions with changes to the config file (or other changes you need to act on) are listed.

### v1.6.0

- `queries.redact_columns`: new option, see [Query values](#query-values). Without it, the default columns are redacted anyway.
- `queries.report_pattern`: the default now also excludes the framework tables `cache`, `cache_locks`, `job_batches`, `failed_jobs` and `password_reset_tokens`, schema-qualified table names (`"public"."jobs"`), and `insert ignore into` / `insert or ignore into`.
- Also without a published config file: secrets among the query values are now redacted in the log and in the `QueryLogWritten` event. If your listeners or patterns rely on such values, adjust `SQL_REPORTER_QUERIES_REDACT_COLUMNS`, or use the original values in `SqlQuery::$bindings` of a `Writer::shouldReportQuery()` callback.

### v1.5.0

- `formatting.header_fields`: the default is now `datetime,origin,route,status,user,guard,env,agent,ip,referer`. Add the new `route` field after `origin`, and remove `host`, unless you want the reverse DNS lookup of the client IP. Do the same in `SQL_REPORTER_FORMAT_HEADER_FIELDS`, if you set it in `.env`.
- `formatting.redact_url_parameters`: new option, see [Sensitive data](#sensitive-data). Without it, the default parameters are redacted anyway.
- `formatting.geoip`: new option. Without it, there is no GeoIP lookup.
- Also without a published config file: GeoIP is no longer enabled just because `stevebauman/location` is installed. To keep the country of the client IP in the log header, add `SQL_REPORTER_FORMAT_GEOIP=true` to your `.env`.

### v1.4.0

- `queries.log_jobs`: new option. Without it, queued jobs are not logged, which is the default anyway.
- `queries.exclude_commands`: new option. Without it, no commands are excluded, so long-running vendor commands like `horizon` or `pulse:check` collect all their queries in memory.
- `queries.report_pattern`: the default now also excludes the framework tables with PostgreSQL / SQLite identifier quoting (`"jobs"`), not only with MySQL backticks.

### v1.2.3

- `formatting.header_datetime_format`: new option. Without it, logging fails if the `datetime` header field is enabled.
- `formatting.header_fields`: the default now starts with `datetime`, followed by `origin`.

### v1.2.0

- `queries.report_pattern`: new option. Without it, all logged queries are reported (`QueryLogWritten` event), including `SELECT` queries.
- `formatting.header_fields`: new `guard` field, after `user`.

## Changes

All changes are listed in [CHANGELOG](CHANGELOG.md)

## Caveats

- If your application crashes, this package will not log any queries, as logging is only triggered at the end of the request cycle. As alternative, you could use [mnabialek/laravel-sql-logger](https://github.com/mnabialek/laravel-sql-logger) which triggers sql logging on each query execution.
- It's currently not possible to log slow queries into a separate logfile. I wanted to keep that package simple.
- Only queries of the default database connection are logged.
- Logging never breaks your application: if the log file can't be written, or a `QueryLogWritten` listener fails, the error is passed to your exception handler (e.g. logged) and the request, command or job continues. By default, web requests and Artisan commands write to the same log file. If they run as different users (e.g. `www-data` and a deploy user), use a separate console log file with `SQL_REPORTER_CONSOLE_SUFFIX`.
- Values that can't be embedded as a string, e.g. binary data, are logged as hex literals (`x'00ff'` for MySQL and SQLite).

## TODO

- [ ] Integrate Coveralls.io and add test coverage status badge to README
- [ ] Add browser type information to log headers, maybe using [hisorange/browser-detect](https://github.com/hisorange/browser-detect)

## Authors

Author of this awesome package is **[Philip Iezzi (Onlime GmbH)](https://www.onlime.ch/)**.

Large parts of this package were ported from the original [mnabialek/laravel-sql-logger](https://github.com/mnabialek/laravel-sql-logger). Credits go to **[Marcin Nabiałek](http://marcin.nabialek.org/en/)**.
Please star his great package on GitHub! You may use `composer thanks` for this.

## License

This package is licenced under the [MIT license](LICENSE) however support is more than welcome.