<?php

declare(strict_types=1);

namespace App\Incidents\Listeners;

use App\Alerting\Events\AlertDeliveredEvent;
use App\Incidents\Storage\IncidentRecorder;

final readonly class RecordAlertInTimeline
{
    public function __construct(
        private IncidentRecorder $incidents,
    ) {}

    public function handle(AlertDeliveredEvent $event): void
    {
        if ($event->incidentId === null) {
            return;
        }

        $this->incidents->recordAlert($event->incidentId, $event->wasSent(), $event->finishedAt, [
            'channel_id' => $event->channelId,
            'channel_type' => $event->channelType->value,
            'kind' => $event->kind->value,
            'attempts' => $event->attempts,
        ]);
    }
}
