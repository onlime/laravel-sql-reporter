# CHANGELOG

## [v0.9.2 (Unreleased)](https://github.com/onlime/laravel-sql-reporter/compare/v0.9.1...main)

## [v0.9.1 (2021-06-03)](https://github.com/onlime/laravel-sql-reporter/releases/tag/v0.9.1)

### Added
- Added new config param `queries.exclude_pattern` (env var `SQL_REPORTER_QUERIES_EXCLUDE_PATTERN`) to narrow down queries to be logged without bloating include pattern regex.
- Added unit tests for `Writer`, testing query include/exclude patterns and min exec time.

### Changed
- Renamed `SQL_REPORTER_QUERIES_PATTERN` env var to `SQL_REPORTER_QUERIES_INCLUDE_PATTERN`
- Renamed methods in `Writer` for clarity.
- Improved testability of `Writer::writeQuery()` by returning true if query was written to log.

## [v0.9 (2021-06-02)](https://github.com/onlime/laravel-sql-reporter/releases/tag/v0.9)

- Initial release
