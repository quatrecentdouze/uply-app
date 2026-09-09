<?php

declare(strict_types=1);

namespace App\Realtime\Listeners;

use App\Incidents\Events\IncidentResolvedEvent;
use App\Realtime\Broadcasts\IncidentResolvedBroadcast;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class BroadcastIncidentResolved
{
    public function __construct(
        private Dispatcher $events,
    ) {}

    public function handle(IncidentResolvedEvent $event): void
    {
        $this->events->dispatch(new IncidentResolvedBroadcast(
            $event->organizationId,
            $event->incidentId,
            $event->monitorId,
            $event->resolvedAt->utc()->toIso8601ZuluString(),
            $event->durationSeconds,
        ));
    }
}
