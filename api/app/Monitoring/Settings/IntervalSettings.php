<?php

declare(strict_types=1);

namespace App\Monitoring\Settings;

final readonly class IntervalSettings
{
    public function __construct(
        public array $allowedSeconds,
        public int $defaultSeconds,
    ) {}

    public function allows(int $seconds): bool
    {
        return in_array($seconds, $this->allowedSeconds, true);
    }
}
