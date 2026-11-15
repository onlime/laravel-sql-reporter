<?php

declare(strict_types=1);

use Onlime\LaravelSqlReporter\Support\QueryColumns;

it('determines the table and column of each binding', function (string $sql, array $expected, string $prefix = '') {
    expect(QueryColumns::resolve($sql, $prefix))->toBe($expected);
})->with([
    'select (MySQL)' => [
        'select * from `users` where `email` = ? and `active` = ? limit 1',
        [['users', 'email'], ['users', 'active']],
    ],
    'qualified columns and an in list (PostgreSQL)' => [
        'select * from "users" inner join "teams" on "teams"."id" = "users"."team_id" where "teams"."name" in (?, ?, ?) and "users"."id" > ?',
        [['teams', 'name'], ['teams', 'name'], ['teams', 'name'], ['users', 'id']],
    ],
    'between' => [
        'select * from "orders" where "total" between ? and ? and "status" not like ?',
        [['orders', 'total'], ['orders', 'total'], ['orders', 'status']],
    ],
    'update' => [
        'update `users` set `password` = ?, `remember_token` = ?, `updated_at` = ? where `id` = ?',
        [['users', 'password'], ['users', 'remember_token'], ['users', 'updated_at'], ['users', 'id']],
    ],
    'delete' => [
        'delete from "sessions" where "id" = ?',
        [['sessions', 'id']],
    ],
    'insert of several rows' => [
        'insert into "users" ("name", "location", "password") values (?, ST_GeomFromText(?), ?), (?, ST_GeomFromText(?), ?)',
        [['users', 'name'], ['users', 'location'], ['users', 'password'], ['users', 'name'], ['users', 'location'], ['users', 'password']],
    ],
    'insert with an expression among the values' => [
        'insert into "users" ("name", "created_at", "password") values (?, now(), ?)',
        [['users', null], ['users', null]],
    ],
    'upsert (MySQL)' => [
        'insert into `cache` (`key`, `value`, `expiration`) values (?, ?, ?) on duplicate key update `value` = values(`value`)',
        [['cache', 'key'], ['cache', 'value'], ['cache', 'expiration']],
    ],
    'upsert with a binding in its update clause (PostgreSQL)' => [
        'insert into "cache" ("key", "value") values (?, ?) on conflict ("key") do update set "value" = ? returning "key"',
        [['cache', 'key'], ['cache', 'value'], ['cache', 'value']],
    ],
    'insert ignore (MySQL)' => [
        'insert ignore into `cache` (`key`, `value`) values (?, ?)',
        [['cache', 'key'], ['cache', 'value']],
    ],
    'SQL Server' => [
        'select * from [users] where [email] = ?',
        [['users', 'email']],
    ],
    'unquoted, with an expression' => [
        'select * from users where lower(email) = ? and password = ?',
        [['users', null], ['users', 'password']],
    ],
    'string literals and escaped ? operators, like Laravel' => [
        'select * from "posts" where "title" = \'it\'\'s ?\' and "body" = \'a\\\'?\' and "tags" ?? ? and "slug" = ?',
        [['posts', null], ['posts', 'slug']],
    ],
    'schema-qualified table' => [
        'update "public"."sessions" set "payload" = ? where "id" = ?',
        [['sessions', 'payload'], ['sessions', 'id']],
    ],
    'without table prefix' => [
        'select * from "app_sessions" where "app_sessions"."id" = ? and "user_id" = ?',
        [['sessions', 'id'], ['sessions', 'user_id']],
        'app_',
    ],
    'without table' => [
        'select ? as "name"',
        [[null, null]],
    ],
]);

it('handles long in lists', function () {
    $columns = QueryColumns::resolve('select * from "users" where "id" in ('.implode(', ', array_fill(0, 10000, '?')).')');

    expect($columns)->toHaveCount(10000)
        ->and(array_unique($columns, SORT_REGULAR))->toBe([['users', 'id']]);
});
