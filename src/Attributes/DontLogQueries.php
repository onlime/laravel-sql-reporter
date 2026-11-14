<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter\Attributes;

use Attribute;

/**
 * Don't log the queries of an Artisan command or queued job (also a queued listener, notification or mailable).
 * No log entry is written, and no QueryLogWritten event is dispatched.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class DontLogQueries {}
