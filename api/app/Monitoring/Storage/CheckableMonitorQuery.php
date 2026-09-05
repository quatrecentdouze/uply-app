<?php

declare(strict_types=1);

namespace App\Monitoring\Storage;

use App\Monitoring\Models\Monitor;
use App\Monitoring\Rules\CheckScheduleRules;
use Carbon\CarbonImmutable;

final class CheckableMonitorQuery
{
    public function findCheckable(string $monitorId, CarbonImmutable $scheduledAt): ?Monitor
    {
        $monitor = Monitor::query()->whereKey($monitorId)->where('is_paused', false)->first();
        if ($monitor === null || ! CheckScheduleRules::slotIsOpen($monitor->last_checked_at, $scheduledAt)) {
            return null;
        }

        return $monitor;
    }
}
