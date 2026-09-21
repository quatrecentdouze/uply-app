<?php

declare(strict_types=1);

use App\Incidents\Enums\IncidentEventKind;
use App\Incidents\Models\Incident;
use App\Incidents\Models\IncidentEvent;
use App\Monitoring\Models\Monitor;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use Tests\Support\Members;

function incidentUrl(Incident $incident): string
{
    return "/api/v1/organizations/{$incident->organization_id}/incidents/{$incident->id}";
}

function timelineEntry(string $kind, string $occurredAt): array
{
    return [
        'kind' => $kind,
        'occurred_at' => $occurredAt,
        'failure_kind' => null,
        'http_status' => null,
        'message' => null,
        'duration_seconds' => null,
        'channel_id' => null,
        'channel_type' => null,
        'alert_kind' => null,
        'attempts' => null,
    ];
}

beforeEach(function (): void {
    $this->clock = freezeClock('2027-03-10 12:00:00');
    $this->organization = Organization::factory()->create();
    $monitor = Monitor::factory()->state(['organization_id' => $this->organization->id])->create(['name' => 'API']);
    $startedAt = $this->clock->now()->subMinutes(10);
    $this->incident = Incident::factory()->create(['monitor_id' => $monitor->id, 'started_at' => $startedAt, 'resolved_at' => $startedAt->addMinutes(4)]);
    $timeline = [
        [IncidentEventKind::Resolved, 240, ['duration_seconds' => 240]],
        [IncidentEventKind::Opened, 0, ['failure_kind' => 'http_status', 'http_status' => 500, 'message' => 'HTTP 500']],
        [IncidentEventKind::AlertSent, 61, ['channel_id' => '01channel', 'channel_type' => 'email', 'kind' => 'down', 'attempts' => 1]],
    ];
    foreach ($timeline as [$kind, $seconds, $data]) {
        IncidentEvent::query()->create(['incident_id' => $this->incident->id, 'kind' => $kind, 'occurred_at' => $startedAt->addSeconds($seconds), 'data' => $data]);
    }
});

it('shows an incident with its timeline in order to every role', function (Role $role): void {
    $this->actingAs(Members::seat($this->organization, $role))->getJson(incidentUrl($this->incident))
        ->assertOk()
        ->assertJsonPath('data.id', $this->incident->id)
        ->assertJsonPath('data.monitor.name', 'API')
        ->assertJsonPath('data.duration_seconds', 240)
        ->assertJsonCount(3, 'data.timeline')
        ->assertJsonPath('data.timeline.0', [...timelineEntry('opened', '2027-03-10T11:50:00Z'), 'failure_kind' => 'http_status', 'http_status' => 500, 'message' => 'HTTP 500'])
        ->assertJsonPath('data.timeline.1', [...timelineEntry('alert_sent', '2027-03-10T11:51:01Z'), 'channel_id' => '01channel', 'channel_type' => 'email', 'alert_kind' => 'down', 'attempts' => 1])
        ->assertJsonPath('data.timeline.2', [...timelineEntry('resolved', '2027-03-10T11:54:00Z'), 'duration_seconds' => 240]);
})->with([Role::Owner, Role::Admin, Role::Member, Role::Viewer]);

it('hides an incident from a non member and from another organization', function (): void {
    $foreign = Incident::factory()->create();

    $this->actingAs(Members::outsider())->getJson(incidentUrl($this->incident))->assertNotFound();
    $this->actingAs(Members::seat($this->organization, Role::Owner))
        ->getJson("/api/v1/organizations/{$this->organization->id}/incidents/{$foreign->id}")
        ->assertNotFound();
});
