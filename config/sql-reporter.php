<?php

return [

    'general' => [
        /*
         * Directory where log files will be saved
         */
        'directory' => storage_path((string) env('SQL_REPORTER_DIRECTORY', 'logs/sql')),

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
         * - datetime: date and time when the first query was executed
         * - origin: where this query coming from - method and URL of a request (with sensitive parameters redacted,
         *   see 'redact_url_parameters'), Artisan command or queued job
         * - route: (request-only) name of the route, if it has one
         * - status: total query count and execution time for all queries
         * - user: username of authenticated user
         * - guard: name of the auth guard (defaults to 'web' if not logged in)
         * - env: application environment
         * - agent: user agent
         * - ip: (request-only) remote user IP, see 'geoip'
         * - host: (request-only) remote user hostname (resolved IP), not enabled by default: the reverse DNS lookup
         *   blocks the PHP process until the DNS server answers, which is up to the owner of the client IP
         * - referer: (request-only) browser referer
         *
         * The request-only fields would simply report some standard/empty data if origin is an Artisan command.
         */
        'header_fields' => array_filter(explode(',', (string) env('SQL_REPORTER_FORMAT_HEADER_FIELDS',
            'datetime,origin,route,status,user,guard,env,agent,ip,referer'
        ))),

        /*
         * URL parameters whose values are replaced by [redacted] in the log header, as they might carry secrets like
         * tokens, signatures of signed URLs or OAuth authorization codes: route parameters (e.g. {token}) and query
         * string parameters in the 'origin' header field, query string parameters in the 'referer' header field.
         * Case-insensitive, with * wildcards. A nested query string parameter like filter[token] matches 'token' too.
         * In .env, separate them by commas. Set it to an empty value to redact no parameters at all.
         */
        'redact_url_parameters' => env('SQL_REPORTER_FORMAT_REDACT_URL_PARAMETERS', [
            '*token*', '*password*', '*secret*', '*signature*', '*key', 'code', 'state', 'sig',
        ]),

        /*
         * Whether to append the country to the client IP ('ip' header field), using stevebauman/location, which needs
         * to be installed. Be aware that its default driver sends the client IP to ip-api.com over plain HTTP.
         */
        'geoip' => env('SQL_REPORTER_FORMAT_GEOIP', false),

        /*
         * Header datetime format.
         */
        'header_datetime_format' => env('SQL_REPORTER_FORMAT_HEADER_DATETIME_FORMAT', 'Y-m-d H:i:s P'),

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
         * Whether queries of queued jobs should be logged, each job separately with its own header ('(job) <name>'
         * origin) and QueryLogWritten event. Jobs on a sync queue are always logged as part of the request or
         * command that dispatched them. See README for details and possible impacts before enabling this.
         */
        'log_jobs' => env('SQL_REPORTER_QUERIES_LOG_JOBS', false),

        /*
         * Artisan commands whose queries should not be logged at all, like with the #[DontLogQueries] attribute.
         * Their query log is disabled while they run, so use it for long-running commands (daemons), which would
         * otherwise collect all their queries in memory, and for vendor commands. Command names (e.g. 'pulse:check')
         * or fully-qualified class names, both with * wildcards. A class name also matches its subclasses.
         * In .env, separate them by commas. Queue workers are handled separately, see 'log_jobs'.
         */
        'exclude_commands' => env('SQL_REPORTER_QUERIES_EXCLUDE_COMMANDS', [
            'Illuminate\Console\Scheduling\ScheduleWorkCommand', // schedule:work
            'Laravel\Horizon\Console\HorizonCommand', // horizon
            'Laravel\Horizon\Console\SupervisorCommand', // horizon:supervisor
            'Laravel\Pulse\Commands\CheckCommand', // pulse:check
            'Laravel\Pulse\Commands\WorkCommand', // pulse:work
            'Laravel\Reverb\Servers\Reverb\Console\Commands\StartServer', // reverb:start
        ]),

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
         * Pattern which is used to detect DML queries to report (QueryLogWritten event).
         * (Data Manipulation Language - INSERT, UPDATE, DELETE, etc.)
         * By default, it excludes SELECT queries, transactions, and modifications of some framework tables,
         * with any identifier quoting (MySQL `jobs`, PostgreSQL / SQLite "jobs").
         */
        'report_pattern' => env(
            'SQL_REPORTER_QUERIES_REPORT_PATTERN',
            '/^(?!select\s|start transaction|commit|(insert into|update|delete from) [`"]?(sessions|jobs|bans|logins)\b).*/i'
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
