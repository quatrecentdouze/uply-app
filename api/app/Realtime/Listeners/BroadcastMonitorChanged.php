<?php

declare(strict_types=1);

namespace App\Realtime\Listeners;

use App\Monitoring\Events\MonitorChangedEvent;
use App\Realtime\Broadcasts\MonitorChangedBroadcast;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class BroadcastMonitorChanged
{
    public function __construct(
        private Dispatcher $events,
    ) {}

    public function handle(MonitorChangedEvent $event): void
    {
        $this->events->dispatch(new MonitorChangedBroadcast($event->organizationId, $event->monitorId, $event->change->value));
    }
}
