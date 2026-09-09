<?php

declare(strict_types=1);

namespace App\Realtime\Listeners;

use App\Monitoring\Events\CheckCompletedEvent;
use App\Realtime\Broadcasts\MonitorCheckedBroadcast;
use App\Realtime\Broadcasts\MonitorStateChangedBroadcast;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class BroadcastCheckCompleted
{
    public function __construct(
        private Dispatcher $events,
    ) {}

    public function handle(CheckCompletedEvent $event): void
    {
        $checkedAt = $event->checkedAt->utc()->toIso8601ZuluString();
        $this->events->dispatch(new MonitorCheckedBroadcast(
            $event->organizationId,
            $event->monitorId,
            $checkedAt,
            $event->outcome->value,
            $event->responseMs,
            $event->state->value,
        ));

        if ($event->state !== $event->previousState) {
            $this->events->dispatch(new MonitorStateChangedBroadcast($event->organizationId, $event->monitorId, $event->state->value, $checkedAt));
        }
    }
}
