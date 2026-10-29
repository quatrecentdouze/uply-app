<?php

declare(strict_types=1);

namespace App\Incidents\Listeners;

use App\Incidents\Storage\IncidentRecorder;
use App\Incidents\Values\IncidentOpening;
use App\Monitoring\Events\MonitorWentDownEvent;

final readonly class OpenIncidentOnMonitorDown
{
    public function __construct(
        private IncidentRecorder $incidents,
    ) {}

    public function handle(MonitorWentDownEvent $event): void
    {
        $this->incidents->open(new IncidentOpening(
            organizationId: $event->organizationId,
            monitorId: $event->monitorId,
            startedAt: $event->downSince,
            causeFailureKind: $event->failureKind->value,
            causeHttpStatus: $event->httpStatus,
            causeMessage: $event->errorMessage,
            monitorFlapping: $event->flapping,
        ));
    }
}
