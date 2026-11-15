<?php

declare(strict_types=1);

arch('strict types')
    ->expect('Onlime\LaravelSqlReporter')
    ->toUseStrictTypes();

arch('php')->preset()->php();

arch('security')->preset()->security();
