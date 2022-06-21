# CHANGELOG

## [1.0.x (Unreleased)](https://github.com/onlime/laravel-sql-reporter/compare/v1.0.0...main)

- Allow bindings to be null.

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
