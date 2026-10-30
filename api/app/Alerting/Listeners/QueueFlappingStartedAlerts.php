<?php

declare(strict_types=1);

namespace App\Alerting\Listeners;

use App\Alerting\Enums\DeliveryKind;
use App\Alerting\Jobs\AlertQueue;
use App\Alerting\Messages\AlertMessageBuilder;
use App\Monitoring\Contracts\MonitorDirectory;
use App\Monitoring\Events\MonitorFlappingStartedEvent;

final readonly class QueueFlappingStartedAlerts
{
    public function __construct(
        private MonitorDirectory $monitors,
        private AlertMessageBuilder $messages,
        private AlertQueue $alerts,
    ) {}

    public function handle(MonitorFlappingStartedEvent $event): void
    {
        $monitor = $this->monitors->find($event->monitorId);
        if ($monitor === null) {
            return;
        }

        $this->alerts->queueForFlapping(
            $event->monitorId,
            DeliveryKind::FlappingStarted,
            $event->flappingSince,
            $this->messages->flappingStarted($monitor, $event->changes, $event->windowMinutes, $event->flappingSince),
        );
    }
}
