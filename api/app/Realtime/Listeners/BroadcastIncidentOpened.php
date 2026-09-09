<?php

declare(strict_types=1);

namespace App\Realtime\Listeners;

use App\Incidents\Events\IncidentOpenedEvent;
use App\Realtime\Broadcasts\IncidentOpenedBroadcast;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class BroadcastIncidentOpened
{
    public function __construct(
        private Dispatcher $events,
    ) {}

    public function handle(IncidentOpenedEvent $event): void
    {
        $this->events->dispatch(new IncidentOpenedBroadcast(
            $event->organizationId,
            $event->incidentId,
            $event->monitorId,
            $event->startedAt->utc()->toIso8601ZuluString(),
        ));
    }
}
