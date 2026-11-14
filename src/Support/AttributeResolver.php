<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter\Support;

use Illuminate\Console\Application as Artisan;
use Illuminate\Support\Reflector;
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
     * Get the classes of a queued job: its display name, which is the class of the job, queued listener,
     * notification or mailable, and the queued job class (e.g. CallQueuedListener).
     *
     * @return list<class-string>
     */
    public function jobClasses(string $displayName, string $jobClass): array
    {
        return array_values(array_unique(array_filter([$displayName, $jobClass], class_exists(...))));
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
            if ($this->cache[$class.'@'.$attribute] ??= Reflector::getClassAttribute($class, $attribute, ascend: true) !== null) {
                return true;
            }
        }

        return false;
    }
}
