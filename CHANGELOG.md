# CHANGELOG

## [v1.5.0 (Unreleased)](https://github.com/onlime/laravel-sql-reporter/compare/v1.4.0...main)

- Security | A single query value that can't be escaped as a string (one with a null byte or invalid UTF-8, e.g. binary data) made logging fail for the whole request, command or job. A client could keep its queries from being logged and reported (`QueryLogWritten` event), simply by sending such a value in any parameter. Each query is now built separately, and these values are logged as hex literals (e.g. `x'00ff'`). Queries logged without execution time (`Connection::logQuery()`) no longer make logging fail either.
- Security | New `SQL_REPORTER_FORMAT_REDACT_URL_PARAMETERS` option: the values of sensitive route parameters and query string parameters are replaced by `[redacted]` in the `origin` header field, e.g. `GET https://example.com/reset-password/[redacted]?email=alice%40example.com&signature=[redacted]`. Secrets like the token of a password reset link, API tokens (`?api_token=`), signatures of signed URLs and OAuth authorization codes are no longer logged and reported. In the `referer` header field, its query string parameters are redacted. Defaults to `*token*,*password*,*secret*,*signature*,*key,code,state,sig` (case-insensitive, with wildcards), also if your published config file doesn't have this option yet. See [Sensitive data](README.md#sensitive-data).
- Security | The `host` header field is no longer enabled by default. Its reverse DNS lookup blocks the PHP process until the DNS server of the client IP answers, which a client controlling it could abuse to tie up PHP workers. Add it to `SQL_REPORTER_FORMAT_HEADER_FIELDS` if you still need it.
- Security | The GeoIP lookup of the client IP is no longer enabled automatically if `stevebauman/location` is installed, as its default driver sends the client IP to ip-api.com over plain HTTP. Enable it with the new `SQL_REPORTER_FORMAT_GEOIP=true` option.
- Feature | New `route` header field with the name of the request's route, if it has one, right after the `origin`. Enabled by default.
- If you published the config file, add the new `redact_url_parameters` and `geoip` options to it, add `route` to its `header_fields`, and remove `host` unless you need it. The same applies to `SQL_REPORTER_FORMAT_HEADER_FIELDS`, if you set it in `.env`.
- README: New [Sensitive data](README.md#sensitive-data) section.

## [v1.4.0 (2026-11-15)](https://github.com/onlime/laravel-sql-reporter/compare/v1.3.6...v1.4.0)

- PHP 8.6 support
- Fix | Queries of Artisan commands were not logged since v1.3.2, because Laravel invoked the deferred logging callback before it was registered. Commands are now logged right away, requests are still logged after the response has been sent.
- Queue workers no longer collect the queries of all processed jobs in memory: their query log is flushed before and after each job, and the worker command itself is no longer logged (also `queue:work --once` and the workers of `queue:listen`).
- Only the outermost Artisan command is logged. Commands called with `Artisan::call()` by a command or a queued job are logged as part of it, instead of logging the queries collected so far once more.
- Feature | New `SQL_REPORTER_QUERIES_EXCLUDE_COMMANDS` option to exclude Artisan commands by name or class name (both with wildcards, class names also match subclasses). The query log is disabled while an excluded command runs. By default, it excludes long-running vendor commands, which would otherwise collect all their queries in memory: `schedule:work`, `horizon`, `horizon:supervisor`, `pulse:check`, `pulse:work` and `reverb:start`.
- Feature | New `SQL_REPORTER_QUERIES_LOG_JOBS` option (default: `false`) to log the queries of each queued job separately, with a `(job) App\Jobs\Foo` origin and its own `QueryLogWritten` event. Timed out jobs are logged as well. See [Queued jobs](README.md#queued-jobs) for the impacts before enabling it.
- Feature | New `#[DontLogQueries]` and `#[DontReportQueries]` attributes to exclude Artisan commands and queued jobs (also queued listeners, notifications and mailables) from logging or from the `QueryLogWritten` event. `#[DontLogQueries]` also disables the query log while a command runs. See [Excluding Artisan commands and queued jobs](README.md#excluding-artisan-commands-and-queued-jobs).
- Logging errors (e.g. an unwritable log file or a failing `QueryLogWritten` listener) are passed to the exception handler, instead of failing the request, command or job. Fixed a race condition when several processes create the log directory at the same time.
- The default `SQL_REPORTER_QUERIES_REPORT_PATTERN` now also excludes the `sessions`, `jobs`, `bans` and `logins` tables with PostgreSQL / SQLite identifier quoting (`"jobs"`), not only with MySQL backticks.
- If you published the config file, add the new `log_jobs` and `exclude_commands` options to it.
- Fix | The logged query could differ from the executed one, as `\n` (e.g. an escaped line break in a JSON value) or placeholders like `[separator]` within the query got replaced by the entry format.
- Fix | `SQL_REPORTER_QUERIES_MIN_EXEC_TIME` set in `.env` caused a `TypeError` since v1.3.2.
- Fix | `Writer::shouldReportQuery()` caused a `TypeError` for callables other than closures (e.g. invokable objects) or callbacks returning a non-bool value (e.g. `preg_match()`). `Writer::shouldReportQuery(null)` removes the callback.
- Fix | No more `ValueError` if none of the configured header fields is supported, no more `TypeError` if the client IP is unknown, and the `user` header field also works for user models preventing access to missing attributes (`Model::preventAccessingMissingAttributes()`).
- Only the configured header fields are resolved, e.g. no more reverse DNS lookup of the client IP if `host` is not configured.
- Require `laravel/framework` instead of `illuminate/support`, `illuminate/filesystem` and `illuminate/container`, as the package depends on Laravel Foundation.
- Fixed CI, which failed since v1.3.6 because of a non-existent `laravel/pint` `^2.0` constraint. Restored `minimum-stability` to `stable`.
- Pest v5 support. Bumped dev dependencies (`laravel/pint` `^1.32`, `mockery/mockery` `^1.6.13`), added `stevebauman/location` `^7.8` for testing the GeoIP lookup.
- Fixed `composer.json`: the `stevebauman/location` suggestion was ignored by Composer, `composer thanks` now stars `mnabialek/laravel-sql-logger`.
- Added static analysis with [Larastan](https://github.com/larastan/larastan) (PHPStan level 8). `Formatter` and `FileName` now depend on the `Application` contract instead of `Container`.
- Tests: 100% code coverage, end-to-end tests with real queries for requests, Artisan commands and queue workers, strict types, architecture tests, namespaced as `Onlime\LaravelSqlReporter\Tests`.
- CI: Added PHP 8.6 to the matrix, code style and static analysis job, pull requests from forks, Composer download cache, coverage on a single job enforcing 100%. Bumped `actions/checkout` to v7 and `actions/cache` to v6, added Dependabot for GitHub Actions.
- Added `.gitattributes` to exclude development files from Composer dist archives.
- PHPUnit configuration: `phpunit.xml` was renamed to `phpunit.xml.dist`, coverage reports are no longer generated on every test run, use `composer test-coverage` instead.
- README: added requirements, fixed the `vendor:publish` command, documented queued jobs, the new attributes and `SQL_REPORTER_QUERIES_EXCLUDE_COMMANDS`.

## [v1.3.6 (2026-03-19)](https://github.com/onlime/laravel-sql-reporter/compare/v1.3.5...v1.3.6)

- Drop Laravel 11 and PHP 8.2 support
- Bump `laravel/pint` to `^2.0`, `pestphp/pest` to `^4.1`
- Bump GitHub Actions: `actions/checkout` to v5, `actions/cache` to v5

## [v1.3.5 (2026-03-01)](https://github.com/onlime/laravel-sql-reporter/compare/v1.3.4...v1.3.5)

- Support Laravel 13

## [v1.3.4 (2025-11-24)](https://github.com/onlime/laravel-sql-reporter/compare/v1.3.3...v1.3.4)

- Support PHP 8.5

## [v1.3.3 (2025-04-16)](https://github.com/onlime/laravel-sql-reporter/compare/v1.3.2...v1.3.3)

- Fix CI/testing for the newly introduced deferred logging.

## [v1.3.2 (2025-04-16)](https://github.com/onlime/laravel-sql-reporter/compare/v1.3.1...v1.3.2)

- Push the logging to the background, after the response has been sent, using Laravel's [`defer()`](https://laravel.com/docs/12.x/helpers#deferred-functions) helper.
- Declare PHP `strict_types` in all classes.

## [v1.3.1 (2025-02-21)](https://github.com/onlime/laravel-sql-reporter/compare/v1.3.0...v1.3.1)

- Support Laravel 12

## [v1.3.0 (2024-10-15)](https://github.com/onlime/laravel-sql-reporter/compare/v1.2.3...v1.3.0)

- Upgrade Pest to v3
- Drop Laravel 10 support and fixed CI

## [v1.2.3 (2024-09-12)](https://github.com/onlime/laravel-sql-reporter/compare/v1.2.2...v1.2.3)

- Made datetime format of formatted header configurable and added TZ offset to default format `Y-m-d H:i:s P`.
- Fix | Prevent duplicate logging when a console command is called from a regular web request (e.g. programmatically executing an Artisan command with `Artisan::call()`). In web context, we're now only logging on `RequestHandled` event, while in console only on `CommandFinished` event.

## [v1.2.2 (2024-03-21)](https://github.com/onlime/laravel-sql-reporter/compare/v1.2.1...v1.2.2)

- The `Writer` object now has a static `shouldReportSqlQuery()` method to define a custom callback for filtering queries included in the `QueryLogWritten` event. by @pascalbaljet in #4
- The `SqlQuery` object is now a `readonly` class, and all the getter methods have been removed. **This is a breaking change.**
- The `SqlQuery` object now includes the unprepared query and bindings.
- Added tests for the reporting mechanism and the `QueryLogWritten` event
- Drops support for PHP 8.1

## [v1.2.1 (2024-03-14)](https://github.com/onlime/laravel-sql-reporter/compare/v1.2.0...v1.2.1)

- Fix | It now runs the whole test suite with a single `vendor/bin/pest` command. by @pascalbaljet in #3
- Moved `FormatterTest` and `SqlLoggerTest` to the Feature directory as they're interacting with the app container.
- Single `Request` mock in `FormatterTest`.

## [v1.2.0 (2024-03-11)](https://github.com/onlime/laravel-sql-reporter/compare/v1.1.0...v1.2.0)

- Feature | Dispatch `QueryLogWritten` event after writing queries to the log, so that the whole query log can be accessed for further processing, e.g. generating reports/notifications.
- Feature | Added auth guard to log headers.
- Laravel 11 support.
- Migrated to Pest for testing (by @pascalbaljet in #2)
- Added GitHub Actions for all supported Laravel and PHP versions.
- Introduced `orchestra/testbench` dev dependency instead of the whole Laravel framework.
- Improved Service Provider: fixed the publish tag and use the regular base `ServiceProvider`.
- Improved `WriterTest` by not mocking the `Config` class but using the real config values.

## [v1.1.0 (2023-07-16)](https://github.com/onlime/laravel-sql-reporter/compare/v1.0.1...v1.1.0)

- Drop Laravel 9 support, require Laravel v10.15 or higher for the new [`DB::getRawQueryLog()`](https://github.com/laravel/framework/pull/47507) support.
- PHP code style fixes by `laravel/pint` v1.10, now using more strict style rules (`laravel` preset).
- Refactored whole codebase from `DB::getQueryLog()` to use the new `DB::getRawQueryLog()` method, so `ReplacesBindings` is no longer needed.
- Replaced [torann/geoip](https://github.com/Torann/laravel-geoip) by [stevebauman/location](https://github.com/stevebauman/location) for optional GeoIP support.
- Improved username detection in `Formatter` headers, so that it works both with default `email` field or custom `username()` method on `User` model.

## [v1.0.1 (2023-02-26)](https://github.com/onlime/laravel-sql-reporter/compare/v1.0.0...v1.0.1)

- Allow bindings to be null.
- Drop Laravel 8 / PHP 8.0 support
- Integrated `laravel/pint` as dev requirement for PHP style fixing
- Support Laravel 10

## [v1.0.0 (2022-02-10)](https://github.com/onlime/laravel-sql-reporter/releases/tag/compare/v0.9.1...v1.0.0)

- Support Laravel 9
- Added some function return types and cleaned up phpdoc comments.

## [v0.9.1 (2021-06-03)](https://github.com/onlime/laravel-sql-reporter/releases/tag/compare/v0.9...v0.9.1)

- Added new config param `queries.exclude_pattern` (env var `SQL_REPORTER_QUERIES_EXCLUDE_PATTERN`) to narrow down queries to be logged without bloating include pattern regex.
- Added unit tests for `Writer`, testing query include/exclude patterns and min exec time.
- Renamed `SQL_REPORTER_QUERIES_PATTERN` env var to `SQL_REPORTER_QUERIES_INCLUDE_PATTERN`
- Renamed methods in `Writer` for clarity.
- Improved testability of `Writer::writeQuery()` by returning true if query was written to log.

## [v0.9 (2021-06-02)](https://github.com/onlime/laravel-sql-reporter/releases/tag/v0.9)

- Initial release
