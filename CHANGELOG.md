# CHANGELOG

## [v1.1.x (Unreleased)](https://github.com/onlime/laravel-sql-reporter/compare/v1.2.0...main)

- ...

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
