<?php

declare(strict_types=1);

namespace App\Monitoring\Storage;

use App\Monitoring\Contracts\MonitorDirectory;
use App\Monitoring\Contracts\MonitorStatus;
use App\Monitoring\Contracts\MonitorView;
use App\Monitoring\Models\Monitor;

final class DatabaseMonitorDirectory implements MonitorDirectory
{
    private const array COLUMNS = ['id', 'organization_id', 'name', 'url', 'state', 'is_paused'];

    public function find(string $monitorId): ?MonitorView
    {
        $monitor = Monitor::query()->whereKey($monitorId)->first(self::COLUMNS);

        return $monitor === null ? null : self::view($monitor);
    }

    public function findMany(array $monitorIds): array
    {
        $ids = array_values(array_filter($monitorIds, 'is_string'));
        if ($ids === []) {
            return [];
        }
        $views = [];
        foreach (Monitor::query()->whereKey($ids)->get(self::COLUMNS) as $monitor) {
            $views[$monitor->id] = self::view($monitor);
        }

        return $views;
    }

    private static function view(Monitor $monitor): MonitorView
    {
        return new MonitorView(
            $monitor->id,
            $monitor->organization_id,
            $monitor->name,
            $monitor->url,
            MonitorStatus::of($monitor->state, $monitor->is_paused),
        );
    }
}
