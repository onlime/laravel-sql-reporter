<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter\Support;

use Illuminate\Console\Application as Artisan;
use Illuminate\Contracts\Queue\Job;
use ReflectionClass;
use Symfony\Component\Console\Command\LazyCommand;
use Throwable;

/**
 * Resolves the classes of Artisan commands and queued jobs, and whether they carry an attribute
 * like DontLogQueries or DontReportQueries (also on a parent class).
 */
class AttributeResolver
{
    /**
     * The Artisan application, to look up commands by name.
     */
    private ?Artisan $artisan = null;

    /**
     * @var array<string, bool>
     */
    private array $cache = [];

    /**
     * Set the Artisan application, to look up commands by name.
     */
    public function setArtisan(Artisan $artisan): void
    {
        $this->artisan = $artisan;
    }

    /**
     * Get the class of an Artisan command by its name.
     *
     * @return list<class-string>
     */
    public function commandClasses(?string $name): array
    {
        if ($this->artisan === null || ! $name) {
            return [];
        }

        try {
            // only resolves this command, unlike Artisan::all() which would instantiate all commands
            $command = $this->artisan->get($name);
        } catch (Throwable) {
            return [];
        }

        if ($command instanceof LazyCommand) {
            $command = $command->getCommand();
        }

        return [$command::class];
    }

    /**
     * Get the classes of a queued job: the job class, and the class of a queued listener, notification or
     * mailable, which is the job's display name.
     *
     * @return list<class-string>
     */
    public function jobClasses(Job $job): array
    {
        $names = [$job->resolveName(), $job->payload()['data']['commandName'] ?? null];

        return array_values(array_unique(array_filter($names, fn ($name) => is_string($name) && class_exists($name))));
    }

    /**
     * Whether one of the classes (or one of their parent classes) carries the attribute.
     *
     * @param  list<class-string>  $classes
     * @param  class-string  $attribute
     */
    public function has(array $classes, string $attribute): bool
    {
        foreach ($classes as $class) {
            if ($this->cache[$class.'@'.$attribute] ??= $this->classHas($class, $attribute)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  class-string  $class
     * @param  class-string  $attribute
     */
    private function classHas(string $class, string $attribute): bool
    {
        for ($reflection = new ReflectionClass($class); $reflection; $reflection = $reflection->getParentClass()) {
            if ($reflection->getAttributes($attribute) !== []) {
                return true;
            }
        }

        return false;
    }
}
