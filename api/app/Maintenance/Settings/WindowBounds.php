<?php

declare(strict_types=1);

namespace App\Maintenance\Settings;

final readonly class WindowBounds
{
    public function __construct(
        public int $maxDurationHours,
        public int $maxDaysAhead,
        public int $startGraceSeconds,
    ) {}
}
