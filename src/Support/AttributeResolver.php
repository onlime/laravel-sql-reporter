<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter\Support;

use Illuminate\Console\Application as Artisan;
use Illuminate\Support\Reflector;
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
     * Set the Artisan application, to look up commands by name.
     */
    public function setArtisan(Artisan $artisan): void
    {
        $this->artisan = $artisan;
    }

    /**
     * Get the class of an Artisan command by its name.
     *
     * @return class-string|null
     */
    public function commandClass(string $name): ?string
    {
        if ($this->artisan === null) {
            return null;
        }

        try {
            // only resolves this command, unlike Artisan::all() which would instantiate all commands
            return $this->artisan->get($name)::class;
        } catch (Throwable) {
            // unknown command
            return null;
        }
    }

    /**
     * Whether one of the classes (or one of their parent classes) carries the attribute.
     *
     * @param  class-string|array<class-string>|null  $classes
     * @param  class-string  $attribute
     */
    public function has(string|array|null $classes, string $attribute): bool
    {
        foreach ((array) $classes as $class) {
            if (Reflector::getClassAttribute($class, $attribute, ascend: true) !== null) {
                return true;
            }
        }

        return false;
    }
}
