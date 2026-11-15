<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\SQLiteConnection;
use Onlime\LaravelSqlReporter\Config;
use Onlime\LaravelSqlReporter\Support\RawQuery;
use Onlime\LaravelSqlReporter\Support\Redactor;

beforeEach(function () {
    $this->rawQuery = new RawQuery(new SQLiteConnection(new PDO('sqlite::memory:')));
});

it('embeds the bindings like Laravel', function () {
    $sql = 'select * from "users" where "name" = ? and "id" = ? and "active" = ? and "created_at" > ? and "note" = \'?\'';

    expect($this->rawQuery->build($sql, ["O'Hara", 7, true, new DateTimeImmutable('2026-11-15 10:00:00')]))
        ->toBe('select * from "users" where "name" = \'O\'\'Hara\' and "id" = 7 and "active" = 1 and "created_at" > \'2026-11-15 10:00:00\' and "note" = \'?\'');
});

it('embeds values that cannot be escaped as a string as hex literals, along with the other values', function (string $value, string $literal) {
    $sql = 'insert into "files" ("name", "content", "note", "size") values (?, ?, \'?\', ?)';

    expect($this->rawQuery->build($sql, ['a.bin', $value, 3]))
        ->toBe("insert into \"files\" (\"name\", \"content\", \"note\", \"size\") values ('a.bin', {$literal}, '?', 3)");
})->with([
    'null byte' => ["a\0b", "x'610062'"],
    'invalid UTF-8' => ["\xff\xfe", "x'fffe'"],
]);

it('embeds a placeholder for values that cannot be escaped at all', function () {
    $stream = fopen('php://memory', 'r');

    expect($this->rawQuery->build('insert into "files" ("content", "tags") values (?, ?)', [$stream, ['a', 'b']]))
        ->toBe('insert into "files" ("content", "tags") values (\'<resource (stream)>\', \'<array>\')');
});

it('embeds a placeholder for binary values if the database driver does not support binary literals', function () {
    // the base connection doesn't support escaping binary values, unlike the MySQL, PostgreSQL, SQLite and SQL Server ones
    $rawQuery = new RawQuery(new Connection(new PDO('sqlite::memory:')));

    expect($rawQuery->build('select * from "files" where "content" = ?', ["a\0b"]))
        ->toBe('select * from "files" where "content" = \'<binary 3 bytes>\'');
});

it('redacts secrets among the values, along with values that cannot be escaped as a string', function () {
    $redactor = new Redactor(new Config(new Repository(['sql-reporter' => ['queries' => ['redact_columns' => ['sessions.id', 'password']]]])));
    $rawQuery = new RawQuery(new SQLiteConnection(new PDO('sqlite::memory:'), '', 'app_'), $redactor);
    $hash = password_hash('secret', PASSWORD_BCRYPT, ['cost' => 4]);

    expect($rawQuery->build('update "app_sessions" set "user_id" = ?, "note" = ? where "id" = ?', [1, $hash, 'abc']))
        ->toBe('update "app_sessions" set "user_id" = 1, "note" = \'$2y$[redacted]\' where "id" = \'[redacted]\'')
        ->and($rawQuery->build('insert into "app_users" ("name", "password") values (?, ?)', ["a\0b", 'plain']))
        ->toBe('insert into "app_users" ("name", "password") values (x\'610062\', \'[redacted]\')')
        // the bindings are found like the grammar does, not within string literals or in escaped ? operators
        ->and($rawQuery->build('select * from "app_users" where "title" = \'?\' and "tags" ?? ? and "password" = ?', ['a', 'b']))
        ->toBe('select * from "app_users" where "title" = \'?\' and "tags" ?? \'a\' and "password" = \'[redacted]\'');
});
