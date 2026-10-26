<?php

declare(strict_types=1);

namespace App\Realtime\Listeners;

use App\Maintenance\Events\MaintenanceChangedEvent;
use App\Realtime\Broadcasts\MaintenanceChangedBroadcast;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class BroadcastMaintenanceChanged
{
    public function __construct(
        private Dispatcher $events,
    ) {}

    public function handle(MaintenanceChangedEvent $event): void
    {
        $this->events->dispatch(new MaintenanceChangedBroadcast($event->organizationId, $event->windowId, $event->change->value, $event->monitorIds));
    }
}
