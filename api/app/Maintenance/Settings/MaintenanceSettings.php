<?php

declare(strict_types=1);

namespace App\Maintenance\Settings;

final readonly class MaintenanceSettings
{
    public function __construct(
        public int $nameMaxLength,
        public MaintenanceLimits $limits,
        public WindowBounds $bounds,
    ) {}
}
