<?php

declare(strict_types=1);

namespace App\Maintenance\Storage;

use App\Maintenance\Contracts\MaintenanceSchedule;
use App\Maintenance\Contracts\ScheduledWindow;
use App\Maintenance\Rules\MaintenanceRules;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

final readonly class DatabaseMaintenanceSchedule implements MaintenanceSchedule
{
    public function __construct(private ConnectionInterface $database) {}

    public function covers(string $monitorId, CarbonImmutable $at): bool
    {
        return $this->windowsOf([$monitorId], $at)
            ->where('windows.starts_at', '<=', $at)
            ->exists();
    }

    public function windowsFor(array $monitorIds, CarbonImmutable $at): array
    {
        $ids = array_values(array_unique(array_filter($monitorIds, 'is_string')));
        if ($ids === []) {
            return [];
        }
        $rows = $this->windowsOf($ids, $at)
            ->select(['links.monitor_id', 'windows.id', 'windows.name', 'windows.starts_at', 'windows.ends_at'])
            ->orderBy('windows.starts_at')
            ->orderBy('windows.id')
            ->get();

        $windows = [];
        foreach ($rows as $row) {
            if (! is_string($row->monitor_id) || isset($windows[$row->monitor_id])) {
                continue;
            }
            if (is_string($row->id) && is_string($row->name) && is_string($row->starts_at) && is_string($row->ends_at)) {
                $startsAt = CarbonImmutable::parse($row->starts_at)->utc();
                $endsAt = CarbonImmutable::parse($row->ends_at)->utc();
                $windows[$row->monitor_id] = new ScheduledWindow($row->id, $row->name, $startsAt, $endsAt, MaintenanceRules::covers($startsAt, $endsAt, $at));
            }
        }

        return $windows;
    }

    private function windowsOf(array $monitorIds, CarbonImmutable $at): Builder
    {
        return $this->database->table('maintenance_window_monitor as links')
            ->join('maintenance_windows as windows', 'windows.id', '=', 'links.maintenance_window_id')
            ->whereIn('links.monitor_id', $monitorIds)
            ->where('windows.ends_at', '>', $at);
    }
}
