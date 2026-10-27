<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use App\Maintenance\Contracts\ScheduledWindow;

final readonly class MaintenanceByMonitor
{
    public function __construct(private array $windows) {}

    public function of(string $monitorId): ?ScheduledWindow
    {
        $window = $this->windows[$monitorId] ?? null;

        return $window instanceof ScheduledWindow ? $window : null;
    }

    public function isActive(string $monitorId): bool
    {
        return $this->of($monitorId)?->isActive === true;
    }
}
