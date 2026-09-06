<?php

declare(strict_types=1);

namespace App\Monitoring\Storage;

use App\Monitoring\Contracts\MonitorDirectory;
use App\Monitoring\Contracts\MonitorView;
use App\Monitoring\Models\Monitor;

final class DatabaseMonitorDirectory implements MonitorDirectory
{
    public function find(string $monitorId): ?MonitorView
    {
        $monitor = Monitor::query()->whereKey($monitorId)->first(['id', 'organization_id', 'name', 'url']);

        return $monitor === null ? null : new MonitorView($monitor->id, $monitor->organization_id, $monitor->name, $monitor->url);
    }
}
