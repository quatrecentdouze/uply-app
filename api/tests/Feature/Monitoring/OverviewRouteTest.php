<?php

declare(strict_types=1);

use App\Monitoring\Enums\MonitorState;
use App\Monitoring\Models\Monitor;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use Tests\Support\CheckHistory;
use Tests\Support\Members;

function overviewUrl(Organization $organization, string $window = ''): string
{
    return "/api/v1/organizations/{$organization->id}/overview".($window === '' ? '' : "?window={$window}");
}

beforeEach(function (): void {
    $this->clock = freezeClock('2027-03-10 12:30:00');
    CheckHistory::keepDaysBefore($this->clock->now(), 1);
    $this->organization = Organization::factory()->create();
    $inOrganization = ['organization_id' => $this->organization->id];
    $this->up = Monitor::factory()->state($inOrganization)->create(['state' => MonitorState::Up]);
    $this->down = Monitor::factory()->state($inOrganization)->create(['state' => MonitorState::Down]);
    Monitor::factory()->state($inOrganization)->create();
    Monitor::factory()->state($inOrganization)->paused()->create(['state' => MonitorState::Up]);
    $this->foreign = Monitor::factory()->create(['state' => MonitorState::Down]);
});

it('counts the monitors by state and gives the uptime of the organization to every role', function (Role $role): void {
    $now = $this->clock->now();
    CheckHistory::result($this->up->id, $now->subHours(2), up: true);
    CheckHistory::result($this->up->id, $now->subHour(), up: true);
    CheckHistory::result($this->down->id, $now->subHour(), up: true);
    CheckHistory::result($this->down->id, $now->subMinutes(10), up: false);
    CheckHistory::result($this->foreign->id, $now->subMinutes(10), up: false);
    CheckHistory::hour($this->up->id, $now->subHour()->startOfHour(), 1, 1, 100, 1, 100, 100);
    CheckHistory::hour($this->down->id, $now->subHour()->startOfHour(), 1, 1, 300, 1, 300, 300);
    CheckHistory::hour($this->down->id, $now->startOfHour(), 1, 0);
    CheckHistory::hour($this->foreign->id, $now->startOfHour(), 9, 0);

    $response = $this->actingAs(Members::seat($this->organization, $role))->getJson(overviewUrl($this->organization))
        ->assertOk()
        ->assertJsonPath('data.window', '24h')
        ->assertJsonPath('data.counts', ['up' => 1, 'down' => 1, 'pending' => 1, 'paused' => 1])
        ->assertJsonPath('data.uptime_percent', 75)
        ->assertJsonCount(24, 'data.series');

    expect($response->json('data.series.22'))->toBe(['start' => '2027-03-10T11:00:00Z', 'uptime_percent' => 100, 'avg_response_ms' => 200])
        ->and($response->json('data.series.23'))->toBe(['start' => '2027-03-10T12:00:00Z', 'uptime_percent' => 0, 'avg_response_ms' => null]);
})->with([Role::Owner, Role::Admin, Role::Member, Role::Viewer]);

it('takes 7 and 30 days from the hourly stats of every monitor of the organization', function (string $window, int $buckets): void {
    CheckHistory::hour($this->up->id, $this->clock->now()->subDays(2)->startOfHour(), 10, 10);
    CheckHistory::hour($this->down->id, $this->clock->now()->subDays(2)->startOfHour(), 10, 5);
    CheckHistory::hour($this->foreign->id, $this->clock->now()->subDays(2)->startOfHour(), 10, 0);

    $this->actingAs(Members::seat($this->organization, Role::Viewer))->getJson(overviewUrl($this->organization, $window))
        ->assertOk()
        ->assertJsonPath('data.window', $window)
        ->assertJsonPath('data.uptime_percent', 75)
        ->assertJsonCount($buckets, 'data.series');
})->with([['7d', 28], ['30d', 30]]);

it('gives an empty organization nothing to count', function (): void {
    $empty = Organization::factory()->create();

    $this->actingAs(Members::seat($empty, Role::Owner))->getJson(overviewUrl($empty))
        ->assertOk()
        ->assertJsonPath('data.counts', ['up' => 0, 'down' => 0, 'pending' => 0, 'paused' => 0])
        ->assertJsonPath('data.uptime_percent', null);
});

it('hides the overview from a non member and refuses an unknown window', function (): void {
    $this->actingAs(Members::outsider())->getJson(overviewUrl($this->organization))->assertNotFound();
    $this->actingAs(Members::seat($this->organization, Role::Viewer))->getJson(overviewUrl($this->organization, '1y'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('window');
});
