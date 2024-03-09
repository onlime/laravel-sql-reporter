<?php

return [

    'general' => [
        /*
         * Directory where log files will be saved
         */
        'directory' => storage_path(env('SQL_REPORTER_DIRECTORY', 'logs/sql')),

        /*
         * Whether execution time in log file should be displayed in seconds
         * (by default it's in milliseconds)
         */
        'use_seconds' => env('SQL_REPORTER_USE_SECONDS', false),

        /*
         * Suffix for Artisan queries logs (if it's empty same logfile will be used for Artisan)
         */
        'console_log_suffix' => env('SQL_REPORTER_CONSOLE_SUFFIX', ''),

        /*
         * Extension for log files
         */
        'extension' => env('SQL_REPORTER_LOG_EXTENSION', '.sql'),
    ],

    'formatting' => [
        /*
         * Header fields, comma-separated. Available options:
         *
         * - origin: where this query coming from - method/request or artisan command
         * - datetime: date and time when the first query was executed
         * - status: total query count and execution time for all queries
         * - user: username of authenticated user
         * - env: application environment
         * - agent: user agent
         * - ip: (request-only) remote user IP
         * - host: (request-only) remote user hostname (resolved IP)
         * - referer: (request-only) browser referer
         *
         * It does not harm to keep all fields enabled. The request-only fields would simply report some standard/empty
         * data if origin is an Artisan command.
         */
        'header_fields' => array_filter(explode(',', env('SQL_REPORTER_FORMAT_HEADER_FIELDS',
            'origin,datetime,status,user,env,agent,ip,host,referer'
        ))),

        /*
         * Single entry format. Available options:
         *
         * - [query_nr] - query number
         * - [datetime] - date and time when query was executed
         * - [query_time] - how long query was executed
         * - [query] - query itself
         * - [separator] - extra separator line to make it easier to see where next query starts
         * - \n - new line separator.
         */
        'entry_format' => env('SQL_REPORTER_FORMAT_ENTRY_FORMAT',
            "-- Query [query_nr] [[query_time]]\n[query]"
        ),
    ],

    'queries' => [
        /*
         * Whether all SQL queries should be logged
         */
        'enabled' => env('SQL_REPORTER_QUERIES_ENABLED', true),

        /*
         * Whether log (for all queries, not for slow queries) should be overridden.
         * It might be useful when you test some functionality, and you want to
         * compare your queries (or number of queries) - be aware that when using
         * AJAX it will override your log file in each request
         */
        'override_log' => env('SQL_REPORTER_QUERIES_OVERRIDE_LOG', false),

        /*
         * Pattern that should be matched to log query. By default, all queries are logged.
         *
         * examples:
         * '#.*#i' will log all queries
         * '/^SELECT.*$/i' will log only SELECT queries
         * '/^(?!SELECT).*$/i' will log all queries other than SELECT (modifying queries)
         */
        'include_pattern' => env('SQL_REPORTER_QUERIES_INCLUDE_PATTERN', '/.*/i'),

        /*
         * Pattern that should not be matched to log query. This limits the queries that were
         * matched by 'include_pattern'. By default, no queries are excluded.
         *
         * examples:
         * '/^$/' don't exclude any queries
         * '/^UPDATE.*last_visit/i' excludes UPDATE queries that modify `last_visit`
         */
        'exclude_pattern' => env('SQL_REPORTER_QUERIES_EXCLUDE_PATTERN', '/^$/'),

        /*
         * Pattern which is used to detect DML queries.
         * (Data Manipulation Language - INSERT, UPDATE, DELETE, etc.)
         * see https://regex101.com/r/vB1QAM/1
         */
        'report_pattern' => env(
            'SQL_REPORTER_QUERIES_REPORT_PATTERN',
            '/^(?!select\s|start transaction|commit|update `sessions`).*/i'
        ),

        /*
         * Only log queries with slow execution time (in milliseconds)
         */
        'min_exec_time' => env('SQL_REPORTER_QUERIES_MIN_EXEC_TIME', 0),

        /*
         * Log file name without extension - elements between [ and ] characters will be parsed
         * according to format used by https://www.php.net/manual/en/function.date.php
         *
         * examples:
         * '[Y-m]-log' (default) results in YYYY-MM-log.sql logfile (monthly rotations)
         * '[Y-m-d]-log' results in YYYY-MM-DD-log.sql logfile (daily rotations)
         * 'query-log' results in query-log.sql (you should use logrotate for rotation)
         */
        'file_name' => env('SQL_REPORTER_QUERIES_FILE_NAME', '[Y-m]-log'),
    ],
];
