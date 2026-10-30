<?php

declare(strict_types=1);

namespace App\Maintenance\Http;

use App\Maintenance\Enums\MaintenanceState;
use App\Maintenance\Http\Requests\MaintenanceWindowFields;
use App\Maintenance\Settings\MaintenanceSettings;
use App\Shared\Meta\MetaSection;

final readonly class MaintenanceMetaSection implements MetaSection
{
    public function __construct(private MaintenanceSettings $settings) {}

    public function key(): string
    {
        return 'maintenance';
    }

    public function describe(): array
    {
        $bounds = $this->settings->bounds;

        return [
            'lengths' => [
                'name' => ['min' => MaintenanceWindowFields::NAME_MIN_LENGTH, 'max' => $this->settings->nameMaxLength],
            ],
            'limits' => [
                'max_open_windows' => $this->settings->limits->maxOpenWindowsPerOrganization,
                'min_monitors' => MaintenanceWindowFields::MONITORS_MIN,
                'max_monitors' => $this->settings->limits->maxMonitorsPerWindow,
            ],
            'max_duration_hours' => $bounds->maxDurationHours,
            'max_days_ahead' => $bounds->maxDaysAhead,
            'start_grace_seconds' => $bounds->startGraceSeconds,
            'states' => array_map(fn (MaintenanceState $state): string => $state->value, MaintenanceState::cases()),
        ];
    }
}
