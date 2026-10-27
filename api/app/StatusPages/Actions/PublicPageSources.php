<?php

declare(strict_types=1);

namespace App\StatusPages\Actions;

use App\Incidents\Contracts\IncidentHistory;
use App\Maintenance\Contracts\MaintenanceSchedule;
use App\Monitoring\Contracts\MonitorDirectory;
use App\Monitoring\Contracts\UptimeQuery;

final readonly class PublicPageSources
{
    public function __construct(
        public MonitorDirectory $monitors,
        public UptimeQuery $uptimes,
        public IncidentHistory $incidents,
        public MaintenanceSchedule $maintenance,
    ) {}
}
