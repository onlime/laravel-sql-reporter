<?php

declare(strict_types=1);

namespace Onlime\LaravelSqlReporter\Attributes;

use Attribute;

/**
 * Log the queries of an Artisan command or queued job (also a queued listener, notification or mailable) as usual,
 * but don't report them: no QueryLogWritten event is dispatched.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class DontReportQueries {}
