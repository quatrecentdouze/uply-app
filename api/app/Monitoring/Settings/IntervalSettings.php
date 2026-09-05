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

    public function shortestSeconds(): int
    {
        $shortest = $this->allowedSeconds[0] ?? null;

        return is_int($shortest) ? $shortest : $this->defaultSeconds;
    }
}
