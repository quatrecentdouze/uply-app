<?php

declare(strict_types=1);

namespace App\Maintenance\Contracts;

use Carbon\CarbonImmutable;

interface MaintenanceSchedule
{
    public function covers(string $monitorId, CarbonImmutable $at): bool;

    public function windowsFor(array $monitorIds, CarbonImmutable $at): array;
}
