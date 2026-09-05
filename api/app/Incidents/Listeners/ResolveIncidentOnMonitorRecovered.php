<?php

declare(strict_types=1);

namespace App\Incidents\Listeners;

use App\Incidents\Storage\IncidentRecorder;
use App\Monitoring\Events\MonitorRecoveredEvent;

final readonly class ResolveIncidentOnMonitorRecovered
{
    public function __construct(
        private IncidentRecorder $incidents,
    ) {}

    public function handle(MonitorRecoveredEvent $event): void
    {
        $this->incidents->resolve($event->monitorId, $event->upSince);
    }
}
