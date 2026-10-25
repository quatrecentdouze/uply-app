<?php

declare(strict_types=1);

namespace App\Maintenance\Storage;

use App\Maintenance\Models\MaintenanceWindow;
use App\Maintenance\Models\MaintenanceWindowMonitor;
use App\Monitoring\Contracts\MonitorDirectory;
use App\Monitoring\Contracts\MonitorView;

final readonly class WindowMonitorWriter
{
    public function __construct(private MonitorDirectory $monitors) {}

    public function replace(MaintenanceWindow $window, array $monitorIds): void
    {
        MaintenanceWindowMonitor::query()->where('maintenance_window_id', $window->id)->delete();
        foreach ($this->monitors->findMany($monitorIds) as $view) {
            if ($view instanceof MonitorView && $view->organizationId === $window->organization_id) {
                MaintenanceWindowMonitor::query()->create(['maintenance_window_id' => $window->id, 'monitor_id' => $view->id]);
            }
        }
    }
}
