<?php

declare(strict_types=1);

namespace App\Alerting\Listeners;

use App\Alerting\Enums\DeliveryKind;
use App\Alerting\Jobs\AlertQueue;
use App\Alerting\Messages\AlertMessageBuilder;
use App\Incidents\Events\IncidentOpenedEvent;
use App\Monitoring\Contracts\MonitorDirectory;

final readonly class QueueDownAlerts
{
    public function __construct(
        private MonitorDirectory $monitors,
        private AlertMessageBuilder $messages,
        private AlertQueue $alerts,
    ) {}

    public function handle(IncidentOpenedEvent $event): void
    {
        $monitor = $event->monitorFlapping ? null : $this->monitors->find($event->monitorId);
        if ($monitor === null) {
            return;
        }

        $this->alerts->queueForIncident(
            $event->incidentId,
            $event->monitorId,
            DeliveryKind::Down,
            $this->messages->down($monitor, $event->incidentId, $event->startedAt, $event->causeFailureKind, $event->causeHttpStatus),
        );
    }
}
