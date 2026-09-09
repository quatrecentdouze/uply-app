<?php

declare(strict_types=1);

namespace App\Providers;

use App\Incidents\Events\IncidentOpenedEvent;
use App\Incidents\Events\IncidentResolvedEvent;
use App\Monitoring\Events\CheckCompletedEvent;
use App\Realtime\Listeners\BroadcastCheckCompleted;
use App\Realtime\Listeners\BroadcastIncidentOpened;
use App\Realtime\Listeners\BroadcastIncidentResolved;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class RealtimeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(CheckCompletedEvent::class, BroadcastCheckCompleted::class);
        Event::listen(IncidentOpenedEvent::class, BroadcastIncidentOpened::class);
        Event::listen(IncidentResolvedEvent::class, BroadcastIncidentResolved::class);
    }
}
