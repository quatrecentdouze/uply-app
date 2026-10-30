<?php

declare(strict_types=1);

namespace App\Alerting\Listeners;

use App\Alerting\Enums\DeliveryKind;
use App\Alerting\Jobs\AlertQueue;
use App\Alerting\Messages\AlertMessageBuilder;
use App\Monitoring\Contracts\MonitorDirectory;
use App\Monitoring\Contracts\MonitorStatus;
use App\Monitoring\Events\MonitorFlappingEndedEvent;

final readonly class QueueFlappingEndedAlerts
{
    public function __construct(
        private MonitorDirectory $monitors,
        private AlertMessageBuilder $messages,
        private AlertQueue $alerts,
    ) {}

    public function handle(MonitorFlappingEndedEvent $event): void
    {
        $monitor = $this->monitors->find($event->monitorId);
        if ($monitor === null) {
            return;
        }

        $this->alerts->queueForFlapping(
            $event->monitorId,
            DeliveryKind::FlappingEnded,
            $event->flappingSince,
            $this->messages->flappingEnded($monitor, MonitorStatus::from($event->state->value), $event->stableMinutes, $event->endedAt),
        );
    }
}
