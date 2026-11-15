<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Onlime\LaravelSqlReporter\Config;
use Onlime\LaravelSqlReporter\Support\Redactor;
use Onlime\LaravelSqlReporter\Writer;

/**
 * @param  array<int, string>|null  $columns  the redact_columns option, null for the defaults
 */
function redactor(?array $columns = null): Redactor
{
    $queries = $columns === null ? [] : ['redact_columns' => $columns];

    return new Redactor(new Config(new Repository(['sql-reporter' => ['queries' => $queries]])));
}

it('redacts password hashes except for their algorithm identifier, in any column', function (string $hash, string $redacted) {
    expect(redactor([])->redact('update "users" set "note" = ?, "password" = ? where "id" = ?', [$hash, $hash, 1]))
        ->toBe([0 => $redacted, 1 => $redacted]);
})->with([
    'bcrypt' => [password_hash('secret', PASSWORD_BCRYPT, ['cost' => 4]), '$2y$[redacted]'],
    'bcrypt $2a$' => ['$2a$10$'.str_repeat('a', 53), '$2a$[redacted]'],
    'bcrypt $2b$' => ['$2b$12$'.str_repeat('Z', 53), '$2b$[redacted]'],
    'Argon2i' => ['$argon2i$v=19$m=65536,t=4,p=1$c29tZXNhbHQ$RdescudvJCsgt3ub+b+dWRWJTmaaJObG', '$argon2i$[redacted]'],
    'Argon2id' => ['$argon2id$v=19$m=65536,t=4,p=1$c29tZXNhbHQ$RdescudvJCsgt3ub+b+dWRWJTmaaJObG', '$argon2id$[redacted]'],
    'Argon2d' => ['$argon2d$v=19$m=65536,t=4,p=1$c29tZXNhbHQ$RdescudvJCsgt3ub+b+dWRWJTmaaJObG', '$argon2d$[redacted]'],
    'MD5-crypt (legacy)' => ['$1$saltsalt$qjXMvbEw8oaL.CzflDugX/', '$1$[redacted]'],
    'SHA-512-crypt (legacy)' => ['$6$rounds=5000$saltsalt$'.str_repeat('a', 86), '$6$[redacted]'],
    'phpass, e.g. WordPress (legacy)' => ['$P$B'.str_repeat('a', 30), '$P$[redacted]'],
    'WordPress 6.8+' => ['$wp$2y$10$'.str_repeat('a', 53), '$wp$[redacted]'],
    'yescrypt' => ['$y$j9T$F5Jx5fExrKuPp53xLKQ..1$X3DX6M94c7o.9agCG9G317fhZg9SqC.5i5rd.RhAtQ7', '$y$[redacted]'],
    'passlib PBKDF2' => ['$pbkdf2-sha256$29000$N2YMIWQsBWBMae09x1jrPQ$1t8iyB2A.WF/Z5JZv.lfCIhXXN33N23OSgQYThBYRfk', '$pbkdf2-sha256$[redacted]'],
]);

it('does not mistake other values for password hashes', function (string $value) {
    expect(redactor([])->redact('update "users" set "note" = ?', [$value]))->toBe([]);
})->with([
    'too short' => ['$2y$hello'],
    'a price' => ['$5$'],
    'with whitespace, e.g. LaTeX' => ['$x$ + $y$ = $z$, where $x$ is the number of items'],
    'identifier not at the start' => ['price: $2y$10$'.str_repeat('a', 53)],
    'not starting with $, e.g. Django' => ['pbkdf2_sha256$260000$c2FsdHNhbHQ$'.str_repeat('a', 44)],
    'hex digest, e.g. MD5' => [md5('secret')],
]);

it('redacts the values of the default columns', function () {
    $sql = 'insert into "users" ("name", "password", "smtp_password", "password_changed_at", "remember_token", "api_token", "two_factor_secret", "token_count") values (?, ?, ?, ?, ?, ?, ?, ?)';

    expect(redactor()->redact($sql, ['Alice', 'plain', 'smtp', '2026-11-15 12:00:00', 'abc', 'def', 'ghi', 5]))
        ->toBe([1 => '[redacted]', 2 => '[redacted]', 4 => '[redacted]', 5 => '[redacted]', 6 => '[redacted]']);
});

it('redacts the values of table.column only in that table', function () {
    $redactor = redactor();

    expect($redactor->redact('update "sessions" set "payload" = ?, "user_id" = ? where "id" = ?', ['YToz', 1, 'abc']))
        ->toBe([0 => '[redacted]', 2 => '[redacted]'])
        ->and($redactor->redact('select * from "users" where "id" = ?', [1]))->toBe([]);
});

it('matches columns case-insensitively, with wildcards', function () {
    expect(redactor(['PASSWORD', 'secret_*', 'Users.Pin'])->redact(
        'update "Users" set "Password" = ?, "SECRET_ANSWER" = ?, "pin" = ?, "name" = ?',
        ['a', 'b', '1234', 'Alice']
    ))->toBe([0 => '[redacted]', 1 => '[redacted]', 2 => '[redacted]']);
});

it('never redacts null values', function () {
    expect(redactor()->redact('update "users" set "remember_token" = ?', [null]))->toBe([]);
});

it('redacts no columns without patterns', function () {
    expect(redactor([])->redact('update "users" set "password" = ?', ['plain']))->toBe([]);
});

it('redacts the values a callback asks for', function () {
    $calls = [];
    Writer::redactBindingUsing(function (mixed $value, ?string $column, ?string $table, string $query) use (&$calls) {
        $calls[] = [$value, $column, $table, $query];

        return $column === 'email';
    });

    $sql = 'select * from "users" where "email" = ? and lower("name") = ?';

    expect(redactor([])->redact($sql, ['alice@example.com', 'alice', null]))->toBe([0 => '[redacted]'])
        ->and($calls)->toBe([
            ['alice@example.com', 'email', 'users', $sql],
            ['alice', null, 'users', $sql],
        ]);
});
