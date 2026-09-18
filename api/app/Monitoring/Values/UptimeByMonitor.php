<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

final readonly class UptimeByMonitor
{
    public function __construct(private array $percents) {}

    public function of(string $monitorId): ?float
    {
        $percent = $this->percents[$monitorId] ?? null;

        return is_float($percent) ? $percent : null;
    }
}
