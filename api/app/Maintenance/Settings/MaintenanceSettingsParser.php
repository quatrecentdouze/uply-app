<?php

declare(strict_types=1);

namespace App\Maintenance\Settings;

use App\Shared\Settings\SettingsReader;

final class MaintenanceSettingsParser
{
    public function parse(string $source, array $values): MaintenanceSettings
    {
        $reader = new SettingsReader($source, $values);

        return new MaintenanceSettings(
            nameMaxLength: $reader->integer('lengths.name_max', 1),
            limits: new MaintenanceLimits(
                maxOpenWindowsPerOrganization: $reader->integer('limits.max_open_windows_per_organization', 1),
                maxMonitorsPerWindow: $reader->integer('limits.max_monitors_per_window', 1),
            ),
            bounds: new WindowBounds(
                maxDurationHours: $reader->integer('bounds.max_duration_hours', 1),
                maxDaysAhead: $reader->integer('bounds.max_days_ahead', 0),
                startGraceSeconds: $reader->integer('bounds.start_grace_seconds', 0),
            ),
        );
    }
}
