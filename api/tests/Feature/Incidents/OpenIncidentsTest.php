<?php

declare(strict_types=1);

use App\Incidents\Contracts\OpenIncident;
use App\Incidents\Contracts\OpenIncidents;
use App\Incidents\Models\Incident;
use App\Monitoring\Models\Monitor;
use Carbon\CarbonImmutable;

it('gives other families every open incident with its cause, oldest first', function (): void {
    $now = CarbonImmutable::parse('2027-03-10 12:00:00', 'UTC');
    $monitor = Monitor::factory()->create();
    $recent = Incident::factory()->create(['monitor_id' => $monitor->id, 'started_at' => $now->subMinutes(5), 'cause_failure_kind' => 'timeout', 'cause_http_status' => null]);
    $older = Incident::factory()->create(['started_at' => $now->subHours(3), 'cause_failure_kind' => 'http_status', 'cause_http_status' => 503]);
    Incident::factory()->create(['monitor_id' => $monitor->id, 'started_at' => $now->subDay(), 'resolved_at' => $now->subDay()->addMinute()]);

    $open = app(OpenIncidents::class)->all();

    expect($open)->toEqual([
        new OpenIncident($older->id, $older->organization_id, $older->monitor_id, $now->subHours(3), 'http_status', 503),
        new OpenIncident($recent->id, $monitor->organization_id, $monitor->id, $now->subMinutes(5), 'timeout', null),
    ]);
});
