<?php

declare(strict_types=1);

namespace App\Maintenance\Settings;

final readonly class MaintenanceLimits
{
    public function __construct(
        public int $maxOpenWindowsPerOrganization,
        public int $maxMonitorsPerWindow,
    ) {}
}
