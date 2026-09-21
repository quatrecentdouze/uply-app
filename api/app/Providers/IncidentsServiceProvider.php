<?php

declare(strict_types=1);

namespace App\Providers;

use App\Alerting\Events\AlertDeliveredEvent;
use App\Incidents\Listeners\OpenIncidentOnMonitorDown;
use App\Incidents\Listeners\RecordAlertInTimeline;
use App\Incidents\Listeners\ResolveIncidentOnMonitorRecovered;
use App\Incidents\Models\Incident;
use App\Incidents\Policies\IncidentPolicy;
use App\Monitoring\Events\MonitorRecoveredEvent;
use App\Monitoring\Events\MonitorWentDownEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class IncidentsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Incident::class, IncidentPolicy::class);

        Event::listen(MonitorWentDownEvent::class, OpenIncidentOnMonitorDown::class);
        Event::listen(MonitorRecoveredEvent::class, ResolveIncidentOnMonitorRecovered::class);
        Event::listen(AlertDeliveredEvent::class, RecordAlertInTimeline::class);
    }
}
