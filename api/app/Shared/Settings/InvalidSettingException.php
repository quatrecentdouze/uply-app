<?php

declare(strict_types=1);

namespace App\Shared\Settings;

use InvalidArgumentException;

final class InvalidSettingException extends InvalidArgumentException
{
    public function __construct(
        public readonly string $source,
        public readonly string $key,
        public readonly string $problem,
    ) {
        parent::__construct("{$source}: {$key} {$problem}");
    }
}
