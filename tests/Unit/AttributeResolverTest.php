<?php

declare(strict_types=1);

use Illuminate\Console\Application as Artisan;
use Illuminate\Container\Container;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Events\Dispatcher;
use Onlime\LaravelSqlReporter\Attributes\DontLogQueries;
use Onlime\LaravelSqlReporter\Attributes\DontReportQueries;
use Onlime\LaravelSqlReporter\Support\AttributeResolver;
use Onlime\LaravelSqlReporter\Tests\Fixtures\Commands\InheritedUnloggedCommand;
use Onlime\LaravelSqlReporter\Tests\Fixtures\Commands\UnloggedCommand;
use Onlime\LaravelSqlReporter\Tests\Fixtures\InsertUserJob;
use Onlime\LaravelSqlReporter\Tests\Fixtures\UnloggedListener;
use Onlime\LaravelSqlReporter\Tests\Fixtures\UnreportedJob;

beforeEach(function () {
    $this->resolver = new AttributeResolver;
    $this->artisan = new Artisan(new Container, new Dispatcher, 'testing');
    $this->artisan->add(new UnloggedCommand);
});

afterEach(function () {
    Artisan::forgetBootstrappers();
});

it('resolves the class of a command by its name', function () {
    $this->resolver->setArtisan($this->artisan);

    expect($this->resolver->commandClass('sql-reporter:unlogged'))->toBe(UnloggedCommand::class);
});

it('resolves no command class without Artisan application, name or matching command', function (bool $withArtisan, string $name) {
    if ($withArtisan) {
        $this->resolver->setArtisan($this->artisan);
    }

    expect($this->resolver->commandClass($name))->toBeNull();
})->with([
    'no Artisan application' => [false, 'sql-reporter:unlogged'],
    'empty name' => [true, ''],
    'unknown command' => [true, 'sql-reporter:unknown'],
]);

it('checks for attributes on the classes and their parent classes', function () {
    expect($this->resolver->has([UnloggedCommand::class], DontLogQueries::class))->toBeTrue()
        ->and($this->resolver->has([InheritedUnloggedCommand::class], DontLogQueries::class))->toBeTrue()
        ->and($this->resolver->has([InheritedUnloggedCommand::class], DontReportQueries::class))->toBeFalse()
        ->and($this->resolver->has([CallQueuedListener::class, UnloggedListener::class], DontLogQueries::class))->toBeTrue()
        ->and($this->resolver->has([InsertUserJob::class, UnreportedJob::class], DontReportQueries::class))->toBeTrue()
        ->and($this->resolver->has([InsertUserJob::class], DontReportQueries::class))->toBeFalse()
        ->and($this->resolver->has([], DontLogQueries::class))->toBeFalse()
        // a single class, e.g. of a command
        ->and($this->resolver->has(UnloggedCommand::class, DontLogQueries::class))->toBeTrue()
        ->and($this->resolver->has(null, DontLogQueries::class))->toBeFalse();
});
