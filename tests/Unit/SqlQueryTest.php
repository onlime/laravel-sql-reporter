<?php

use Onlime\LaravelSqlReporter\SqlQuery;

it('returns valid number', function () {
    $value = 56;
    $query = new SqlQuery($value, 'test', 130);
    expect($query->number())->toBe($value);
});

it('returns valid raw query', function () {
    $value = "SELECT * FROM tests WHERE a = 'test'";
    $query = new SqlQuery(56, $value, 130);
    expect($query->rawQuery())->toBe($value);
});

it('returns valid time', function () {
    $value = 130.0;
    $query = new SqlQuery(56, 'test', $value);
    expect($query->time())->toBe($value);
});
