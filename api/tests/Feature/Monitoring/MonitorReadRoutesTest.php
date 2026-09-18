<?php

declare(strict_types=1);

use App\Monitoring\Enums\MonitorState;
use App\Monitoring\Models\Monitor;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use Tests\Support\CheckHistory;
use Tests\Support\Members;

function monitorsUrl(Organization $organization, ?string $monitorId = null, string $query = ''): string
{
    return "/api/v1/organizations/{$organization->id}/monitors".($monitorId === null ? '' : "/{$monitorId}").$query;
}

beforeEach(function (): void {
    $this->clock = freezeClock('2027-03-10 12:00:00');
    CheckHistory::keepDaysBefore($this->clock->now(), 1);
    $this->organization = Organization::factory()->create();
});

it('lists the monitors of the organization to every role', function (Role $role): void {
    $api = Monitor::factory()->state(['organization_id' => $this->organization->id])->create(['name' => 'API', 'url' => 'https://api.example.com/health']);
    Monitor::factory()->state(['organization_id' => $this->organization->id])->create(['name' => 'Billing']);
    Monitor::factory()->create(['name' => 'Elsewhere']);
    $api->forceFill(['state' => MonitorState::Up, 'last_checked_at' => $this->clock->now()->subSeconds(30), 'last_response_ms' => 87])->save();

    $this->actingAs(Members::seat($this->organization, $role))->getJson(monitorsUrl($this->organization))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $api->id)
        ->assertJsonPath('data.0.name', 'API')
        ->assertJsonPath('data.0.url', 'https://api.example.com/health')
        ->assertJsonPath('data.0.method', 'get')
        ->assertJsonPath('data.0.interval_seconds', 60)
        ->assertJsonPath('data.0.timeout_seconds', 10)
        ->assertJsonPath('data.0.accepted_status_codes', ['200-299'])
        ->assertJsonPath('data.0.follow_redirects', true)
        ->assertJsonPath('data.0.is_paused', false)
        ->assertJsonPath('data.0.state', 'up')
        ->assertJsonPath('data.0.last_checked_at', '2027-03-10T11:59:30Z')
        ->assertJsonPath('data.0.last_response_ms', 87)
        ->assertJsonPath('data.0.uptime_24h', null)
        ->assertJsonPath('data.1.name', 'Billing')
        ->assertJsonPath('meta.total', 2);
})->with([Role::Owner, Role::Admin, Role::Member, Role::Viewer]);

it('gives the uptime of the last 24 hours from the raw results', function (): void {
    $monitor = Monitor::factory()->state(['organization_id' => $this->organization->id])->create();
    $now = $this->clock->now();
    CheckHistory::result($monitor->id, $now->subHours(25), up: false);
    CheckHistory::result($monitor->id, $now->subHours(23), up: true);
    CheckHistory::result($monitor->id, $now->subHours(2), up: true);
    CheckHistory::result($monitor->id, $now->subHour(), up: true);
    CheckHistory::result($monitor->id, $now->subMinute(), up: false, responseMs: null);

    $this->actingAs(Members::seat($this->organization, Role::Viewer))->getJson(monitorsUrl($this->organization))
        ->assertOk()
        ->assertJsonPath('data.0.uptime_24h', 75);
    $this->getJson(monitorsUrl($this->organization, $monitor->id))
        ->assertOk()
        ->assertJsonPath('data.uptime_24h', 75);
});

it('filters by state with paused as a state of its own', function (string $state, array $names): void {
    Monitor::factory()->state(['organization_id' => $this->organization->id])->create(['name' => 'Fresh']);
    Monitor::factory()->state(['organization_id' => $this->organization->id])->create(['name' => 'Healthy', 'state' => MonitorState::Up]);
    Monitor::factory()->state(['organization_id' => $this->organization->id])->create(['name' => 'Broken', 'state' => MonitorState::Down]);
    Monitor::factory()->state(['organization_id' => $this->organization->id])->paused()->create(['name' => 'Resting', 'state' => MonitorState::Down]);

    $response = $this->actingAs(Members::seat($this->organization, Role::Member))
        ->getJson(monitorsUrl($this->organization, query: "?state={$state}"))
        ->assertOk();

    expect(array_column($response->json('data'), 'name'))->toBe($names);
})->with([
    ['up', ['Healthy']],
    ['down', ['Broken']],
    ['pending', ['Fresh']],
    ['paused', ['Resting']],
]);

it('searches the name and the url without wildcards', function (string $search, array $names): void {
    Monitor::factory()->state(['organization_id' => $this->organization->id])->create(['name' => 'Checkout API', 'url' => 'https://shop.example.com/health']);
    Monitor::factory()->state(['organization_id' => $this->organization->id])->create(['name' => 'Docs', 'url' => 'https://docs.example.com/']);
    Monitor::factory()->state(['organization_id' => $this->organization->id])->create(['name' => '100% uptime', 'url' => 'https://status.example.org/']);

    $response = $this->actingAs(Members::seat($this->organization, Role::Viewer))
        ->getJson(monitorsUrl($this->organization, query: '?search='.urlencode($search)))
        ->assertOk();

    expect(array_column($response->json('data'), 'name'))->toBe($names);
})->with([
    ['checkout', ['Checkout API']],
    ['SHOP.example', ['Checkout API']],
    ['example.com', ['Checkout API', 'Docs']],
    ['%', ['100% uptime']],
    ['_', []],
]);

it('paginates the list', function (): void {
    Monitor::factory()->state(['organization_id' => $this->organization->id])->count(3)->sequence(['name' => 'A'], ['name' => 'B'], ['name' => 'C'])->create();
    $this->actingAs(Members::seat($this->organization, Role::Viewer));

    $this->getJson(monitorsUrl($this->organization, query: '?per_page=2&page=2'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'C')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.per_page', 2);
    $this->getJson(monitorsUrl($this->organization, query: '?per_page=101'))->assertUnprocessable()->assertJsonValidationErrors('per_page');
    $this->getJson(monitorsUrl($this->organization, query: '?state=sleeping'))->assertUnprocessable()->assertJsonValidationErrors('state');
});

it('shows one monitor of the organization', function (): void {
    $monitor = Monitor::factory()->state(['organization_id' => $this->organization->id])->create(['name' => 'API']);

    $this->actingAs(Members::seat($this->organization, Role::Viewer))->getJson(monitorsUrl($this->organization, $monitor->id))
        ->assertOk()
        ->assertJsonPath('data.id', $monitor->id)
        ->assertJsonPath('data.name', 'API')
        ->assertJsonPath('data.state', 'pending');
});

it('hides the monitors from a non member and the monitors of other organizations', function (): void {
    $monitor = Monitor::factory()->state(['organization_id' => $this->organization->id])->create();
    $foreign = Monitor::factory()->create();

    $this->actingAs(Members::outsider())->getJson(monitorsUrl($this->organization))->assertNotFound();
    $this->getJson(monitorsUrl($this->organization, $monitor->id))->assertNotFound();
    $this->actingAs(Members::seat($this->organization, Role::Owner))
        ->getJson(monitorsUrl($this->organization, $foreign->id))
        ->assertNotFound();
    $this->getJson(monitorsUrl($this->organization, 'not-a-monitor-id'))->assertNotFound();
});

it('asks for a verified session', function (): void {
    $this->getJson(monitorsUrl($this->organization))->assertUnauthorized();
});
