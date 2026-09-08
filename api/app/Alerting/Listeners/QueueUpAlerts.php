<?php

declare(strict_types=1);

namespace App\Alerting\Listeners;

use App\Alerting\Enums\DeliveryKind;
use App\Alerting\Jobs\AlertQueue;
use App\Alerting\Messages\AlertMessageBuilder;
use App\Incidents\Events\IncidentResolvedEvent;
use App\Monitoring\Contracts\MonitorDirectory;

final readonly class QueueUpAlerts
{
    public function __construct(
        private MonitorDirectory $monitors,
        private AlertMessageBuilder $messages,
        private AlertQueue $alerts,
    ) {}

    public function handle(IncidentResolvedEvent $event): void
    {
        $monitor = $this->monitors->find($event->monitorId);
        if ($monitor === null) {
            return;
        }

        $this->alerts->queueForIncident(
            $event->incidentId,
            $event->monitorId,
            DeliveryKind::Up,
            $this->messages->up($monitor, $event->incidentId, $event->resolvedAt, $event->durationSeconds),
        );
    }
}
