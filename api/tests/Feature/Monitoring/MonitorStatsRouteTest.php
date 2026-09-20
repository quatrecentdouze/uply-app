<?php

declare(strict_types=1);

use App\Monitoring\Models\Monitor;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use Tests\Support\CheckHistory;
use Tests\Support\Members;

function statsUrl(Monitor $monitor, string $window = ''): string
{
    return "/api/v1/organizations/{$monitor->organization_id}/monitors/{$monitor->id}/stats".($window === '' ? '' : "?window={$window}");
}

beforeEach(function (): void {
    $this->clock = freezeClock('2027-03-10 12:30:00');
    CheckHistory::keepDaysBefore($this->clock->now(), 1);
    $this->organization = Organization::factory()->create();
    $this->monitor = Monitor::factory()->state(['organization_id' => $this->organization->id])->create();
});

it('gives the last 24 hours from the raw results with hourly buckets to every role', function (Role $role): void {
    $now = $this->clock->now();
    CheckHistory::result($this->monitor->id, $now->subHours(25), up: false, responseMs: 900);
    CheckHistory::result($this->monitor->id, $now->subHours(3), up: true, responseMs: 100, timings: ['dns_ms' => 4, 'connect_ms' => 10, 'tls_ms' => 20, 'ttfb_ms' => 80]);
    CheckHistory::result($this->monitor->id, $now->subHour(), up: true, responseMs: 300, timings: ['dns_ms' => 6, 'connect_ms' => 12, 'tls_ms' => 30, 'ttfb_ms' => 250]);
    CheckHistory::result($this->monitor->id, $now->subMinutes(5), up: false, responseMs: null);
    CheckHistory::result(Monitor::factory()->create()->id, $now->subMinute(), up: false);
    CheckHistory::hour($this->monitor->id, $now->subHours(3)->startOfHour(), 1, 1, 100, 1, 100, 100);
    CheckHistory::hour($this->monitor->id, $now->subHour()->startOfHour(), 1, 1, 300, 1, 300, 300);
    CheckHistory::hour($this->monitor->id, $now->startOfHour(), 1, 0);

    $response = $this->actingAs(Members::seat($this->organization, $role))->getJson(statsUrl($this->monitor))
        ->assertOk()
        ->assertJsonPath('data.window', '24h')
        ->assertJsonPath('data.uptime_percent', 66.66)
        ->assertJsonPath('data.checks_total', 3)
        ->assertJsonPath('data.response_ms', ['avg' => 200, 'min' => 100, 'max' => 300])
        ->assertJsonPath('data.timings', ['dns_ms' => 5, 'connect_ms' => 11, 'tls_ms' => 25, 'ttfb_ms' => 165])
        ->assertJsonCount(24, 'data.series');

    expect($response->json('data.series.0'))->toBe(['start' => '2027-03-09T13:00:00Z', 'uptime_percent' => null, 'avg_response_ms' => null])
        ->and($response->json('data.series.20'))->toBe(['start' => '2027-03-10T09:00:00Z', 'uptime_percent' => 100, 'avg_response_ms' => 100])
        ->and($response->json('data.series.23'))->toBe(['start' => '2027-03-10T12:00:00Z', 'uptime_percent' => 0, 'avg_response_ms' => null]);
})->with([Role::Owner, Role::Admin, Role::Member, Role::Viewer]);

it('gives 7 days in buckets of 6 hours from the hourly stats', function (): void {
    $now = $this->clock->now();
    CheckHistory::hour($this->monitor->id, $now->subDays(8)->startOfHour(), 100, 0);
    CheckHistory::hour($this->monitor->id, $this->clock->now()->setTime(7, 0), 60, 60, 6000, 60, 50, 200);
    CheckHistory::hour($this->monitor->id, $this->clock->now()->setTime(8, 0), 60, 57, 12000, 60, 40, 900);
    CheckHistory::hour($this->monitor->id, $this->clock->now()->setTime(12, 0), 30, 30, 3000, 30, 90, 110);

    $response = $this->actingAs(Members::seat($this->organization, Role::Viewer))->getJson(statsUrl($this->monitor, '7d'))
        ->assertOk()
        ->assertJsonPath('data.window', '7d')
        ->assertJsonPath('data.checks_total', 150)
        ->assertJsonPath('data.uptime_percent', 98)
        ->assertJsonPath('data.response_ms', ['avg' => 140, 'min' => 40, 'max' => 900])
        ->assertJsonCount(28, 'data.series');

    expect($response->json('data.series.0.start'))->toBe('2027-03-03T18:00:00Z')
        ->and($response->json('data.series.26'))->toBe(['start' => '2027-03-10T06:00:00Z', 'uptime_percent' => 97.5, 'avg_response_ms' => 150])
        ->and($response->json('data.series.27'))->toBe(['start' => '2027-03-10T12:00:00Z', 'uptime_percent' => 100, 'avg_response_ms' => 100]);
});

it('gives 30 days in daily buckets from the hourly stats', function (): void {
    CheckHistory::hour($this->monitor->id, $this->clock->now()->subDays(29)->setTime(0, 0), 24, 12);
    CheckHistory::hour($this->monitor->id, $this->clock->now()->subDays(30)->setTime(23, 0), 50, 0);

    $response = $this->actingAs(Members::seat($this->organization, Role::Viewer))->getJson(statsUrl($this->monitor, '30d'))
        ->assertOk()
        ->assertJsonPath('data.checks_total', 24)
        ->assertJsonPath('data.uptime_percent', 50)
        ->assertJsonPath('data.response_ms', ['avg' => null, 'min' => null, 'max' => null])
        ->assertJsonCount(30, 'data.series');

    expect($response->json('data.series.0'))->toBe(['start' => '2027-02-09T00:00:00Z', 'uptime_percent' => 50, 'avg_response_ms' => null])
        ->and($response->json('data.series.29.start'))->toBe('2027-03-10T00:00:00Z');
});

it('has an empty window without checks', function (): void {
    $this->actingAs(Members::seat($this->organization, Role::Viewer))->getJson(statsUrl($this->monitor, '24h'))
        ->assertOk()
        ->assertJsonPath('data.uptime_percent', null)
        ->assertJsonPath('data.checks_total', 0)
        ->assertJsonPath('data.timings', ['dns_ms' => null, 'connect_ms' => null, 'tls_ms' => null, 'ttfb_ms' => null]);
});

it('refuses an unknown window and hides the stats outside the organization', function (): void {
    $foreign = Monitor::factory()->create();

    $this->actingAs(Members::seat($this->organization, Role::Viewer))->getJson(statsUrl($this->monitor, '90d'))->assertUnprocessable()->assertJsonValidationErrors('window');
    $this->getJson("/api/v1/organizations/{$this->organization->id}/monitors/{$foreign->id}/stats")->assertNotFound();
    $this->actingAs(Members::outsider())->getJson(statsUrl($this->monitor))->assertNotFound();
});
