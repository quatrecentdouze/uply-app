<?php

declare(strict_types=1);

namespace App\Monitoring\Settings;

final readonly class FlappingSettings
{
    public function __construct(
        public int $changes,
        public int $windowMinutes,
        public int $stableMinutes,
    ) {}
}
