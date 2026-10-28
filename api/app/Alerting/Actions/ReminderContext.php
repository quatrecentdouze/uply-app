<?php

declare(strict_types=1);

namespace App\Alerting\Actions;

use App\Alerting\Settings\AlertingSettings;
use App\Incidents\Contracts\OpenIncidents;
use App\Maintenance\Contracts\MaintenanceSchedule;
use App\Monitoring\Contracts\MonitorDirectory;
use App\Shared\Clock;

final readonly class ReminderContext
{
    public function __construct(
        public OpenIncidents $incidents,
        public MonitorDirectory $monitors,
        public MaintenanceSchedule $maintenance,
        public Clock $clock,
        public AlertingSettings $settings,
    ) {}
}
