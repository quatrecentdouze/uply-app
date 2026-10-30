<?php

declare(strict_types=1);

namespace App\Realtime\Listeners;

use App\Monitoring\Events\MonitorFlappingEndedEvent;
use App\Monitoring\Events\MonitorFlappingStartedEvent;
use App\Realtime\Broadcasts\MonitorFlappingBroadcast;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class BroadcastMonitorFlapping
{
    public function __construct(
        private Dispatcher $events,
    ) {}

    public function started(MonitorFlappingStartedEvent $event): void
    {
        $this->events->dispatch(new MonitorFlappingBroadcast($event->organizationId, $event->monitorId, $event->flappingSince->utc()->toIso8601ZuluString()));
    }

    public function ended(MonitorFlappingEndedEvent $event): void
    {
        $this->events->dispatch(new MonitorFlappingBroadcast($event->organizationId, $event->monitorId, null));
    }
}
