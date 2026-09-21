<?php

declare(strict_types=1);

use App\Incidents\Models\Incident;
use App\Monitoring\Models\Monitor;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use Tests\Support\Members;

function incidentsUrl(Organization $organization, string $query = ''): string
{
    return "/api/v1/organizations/{$organization->id}/incidents{$query}";
}

beforeEach(function (): void {
    $this->clock = freezeClock('2027-03-10 12:00:00');
    $this->organization = Organization::factory()->create();
    $this->api = Monitor::factory()->state(['organization_id' => $this->organization->id])->create(['name' => 'API']);
    $this->docs = Monitor::factory()->state(['organization_id' => $this->organization->id])->create(['name' => 'Docs']);
    $now = $this->clock->now();
    $this->resolved = Incident::factory()->create([
        'monitor_id' => $this->api->id,
        'started_at' => $now->subHours(5),
        'resolved_at' => $now->subHours(5)->addSeconds(252),
        'cause_failure_kind' => 'timeout',
        'cause_http_status' => null,
        'cause_message' => 'Operation timed out after 10001 milliseconds',
    ]);
    $this->open = Incident::factory()->create(['monitor_id' => $this->docs->id, 'started_at' => $now->subMinutes(3), 'cause_message' => 'HTTP 500']);
    $this->foreign = Incident::factory()->create(['started_at' => $now->subMinute()]);
});

it('lists the incidents of the organization newest first to every role', function (Role $role): void {
    $this->actingAs(Members::seat($this->organization, $role))->getJson(incidentsUrl($this->organization))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.id', $this->open->id)
        ->assertJsonPath('data.0.monitor', ['id' => $this->docs->id, 'name' => 'Docs'])
        ->assertJsonPath('data.0.started_at', '2027-03-10T11:57:00Z')
        ->assertJsonPath('data.0.resolved_at', null)
        ->assertJsonPath('data.0.duration_seconds', null)
        ->assertJsonPath('data.0.cause', ['failure_kind' => 'http_status', 'http_status' => 500, 'message' => 'HTTP 500'])
        ->assertJsonPath('data.1.id', $this->resolved->id)
        ->assertJsonPath('data.1.monitor.name', 'API')
        ->assertJsonPath('data.1.resolved_at', '2027-03-10T07:04:12Z')
        ->assertJsonPath('data.1.duration_seconds', 252)
        ->assertJsonPath('data.1.cause.failure_kind', 'timeout')
        ->assertJsonMissingPath('data.0.timeline');
})->with([Role::Owner, Role::Admin, Role::Member, Role::Viewer]);

it('filters by state and gives the open count in meta.total', function (string $state, array $ids): void {
    $response = $this->actingAs(Members::seat($this->organization, Role::Viewer))
        ->getJson(incidentsUrl($this->organization, "?state={$state}"))
        ->assertOk()
        ->assertJsonPath('meta.total', count($ids));

    expect(array_column($response->json('data'), 'id'))->toBe(array_map(fn (string $name): string => $this->{$name}->id, $ids));
})->with([
    ['open', ['open']],
    ['resolved', ['resolved']],
]);

it('filters by monitor', function (): void {
    $response = $this->actingAs(Members::seat($this->organization, Role::Viewer))
        ->getJson(incidentsUrl($this->organization, "?monitor={$this->api->id}"))
        ->assertOk();

    expect(array_column($response->json('data'), 'id'))->toBe([$this->resolved->id]);
});

it('never shows the incidents of another organization', function (): void {
    $foreignMonitor = Monitor::query()->whereKey($this->foreign->monitor_id)->sole();
    $this->actingAs(Members::seat($this->organization, Role::Owner));

    $this->getJson(incidentsUrl($this->organization, "?monitor={$foreignMonitor->id}"))
        ->assertOk()
        ->assertJsonCount(0, 'data');
    $this->actingAs(Members::outsider())->getJson(incidentsUrl($this->organization))->assertNotFound();
});

it('validates the filters', function (string $query, string $field): void {
    $this->actingAs(Members::seat($this->organization, Role::Viewer))
        ->getJson(incidentsUrl($this->organization, $query))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    ['?state=closed', 'state'],
    ['?monitor=api', 'monitor'],
    ['?per_page=0', 'per_page'],
]);
