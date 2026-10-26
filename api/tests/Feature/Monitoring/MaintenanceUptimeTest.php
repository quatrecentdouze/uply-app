<?php

declare(strict_types=1);

use App\Monitoring\Models\Monitor;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use Tests\Support\CheckHistory;
use Tests\Support\Members;

beforeEach(function (): void {
    $this->clock = freezeClock('2027-03-10 12:30:00');
    CheckHistory::keepDaysBefore($this->clock->now(), 1);
    $this->organization = Organization::factory()->create();
    $this->monitor = Monitor::factory()->create(['organization_id' => $this->organization->id]);
    $this->quiet = Monitor::factory()->create(['organization_id' => $this->organization->id, 'name' => 'zz quiet']);
    $now = $this->clock->now();
    CheckHistory::result($this->monitor->id, $now->subHours(2), up: true, responseMs: 100, timings: ['dns_ms' => 4, 'connect_ms' => 10, 'tls_ms' => 20, 'ttfb_ms' => 80]);
    CheckHistory::result($this->monitor->id, $now->subHour(), up: false, responseMs: 900, timings: ['dns_ms' => 400, 'connect_ms' => 400, 'tls_ms' => 400, 'ttfb_ms' => 900], inMaintenance: true);
    CheckHistory::result($this->monitor->id, $now->subMinutes(30), up: false, responseMs: null, inMaintenance: true);
    CheckHistory::result($this->quiet->id, $now->subMinutes(10), up: false, responseMs: 500, inMaintenance: true);
    $this->actingAs(Members::seat($this->organization, Role::Viewer));
});

it('leaves the results of a window out of the 24 hour uptime, latency and timings of a monitor', function (): void {
    $this->getJson("/api/v1/organizations/{$this->organization->id}/monitors/{$this->monitor->id}/stats")
        ->assertOk()
        ->assertJsonPath('data.uptime_percent', 100)
        ->assertJsonPath('data.checks_total', 1)
        ->assertJsonPath('data.response_ms', ['avg' => 100, 'min' => 100, 'max' => 100])
        ->assertJsonPath('data.timings', ['dns_ms' => 4, 'connect_ms' => 10, 'tls_ms' => 20, 'ttfb_ms' => 80]);
});

it('gives no 24 hour uptime to a monitor that was only checked in a window', function (): void {
    $this->getJson("/api/v1/organizations/{$this->organization->id}/monitors")
        ->assertOk()
        ->assertJsonPath('data.0.uptime_24h', 100)
        ->assertJsonPath('data.1.name', 'zz quiet')
        ->assertJsonPath('data.1.uptime_24h', null);
});

it('leaves the results of a window out of the 24 hour uptime of the organization', function (): void {
    $this->getJson("/api/v1/organizations/{$this->organization->id}/overview")
        ->assertOk()
        ->assertJsonPath('data.uptime_percent', 100);
});

it('counts only the hourly totals outside the windows over 7 days', function (): void {
    $hour = $this->clock->now()->subDays(2)->startOfHour();
    CheckHistory::hour($this->monitor->id, $hour, 4, 4, 400, 4, 90, 110, maintenance: 56);
    CheckHistory::hour($this->quiet->id, $hour, 0, 0, maintenance: 60);

    $this->getJson("/api/v1/organizations/{$this->organization->id}/monitors/{$this->monitor->id}/stats?window=7d")
        ->assertOk()
        ->assertJsonPath('data.uptime_percent', 100)
        ->assertJsonPath('data.checks_total', 4);
    $this->getJson("/api/v1/organizations/{$this->organization->id}/monitors/{$this->quiet->id}/stats?window=7d")
        ->assertOk()
        ->assertJsonPath('data.uptime_percent', null)
        ->assertJsonPath('data.checks_total', 0);
});
