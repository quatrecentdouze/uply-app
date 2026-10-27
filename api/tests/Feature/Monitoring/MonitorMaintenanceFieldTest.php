<?php

declare(strict_types=1);

use App\Maintenance\Models\MaintenanceWindow;
use App\Maintenance\Models\MaintenanceWindowMonitor;
use App\Monitoring\Models\Monitor;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use Tests\Support\Members;

function maintainedMonitor(Organization $organization, string $name, array $windows): Monitor
{
    $monitor = Monitor::factory()->create(['organization_id' => $organization->id, 'name' => $name]);
    foreach ($windows as [$windowName, $startsAt, $endsAt]) {
        $window = MaintenanceWindow::factory()->between($startsAt, $endsAt)->create(['organization_id' => $organization->id, 'name' => $windowName]);
        MaintenanceWindowMonitor::query()->create(['maintenance_window_id' => $window->id, 'monitor_id' => $monitor->id]);
    }

    return $monitor;
}

beforeEach(function (): void {
    freezeClock('2027-03-10 12:00:00');
    $this->organization = Organization::factory()->create();
    $this->active = maintainedMonitor($this->organization, 'a active', [
        ['Later', '2027-03-11 10:00:00', '2027-03-11 11:00:00'],
        ['Now', '2027-03-10 11:30:00', '2027-03-10 12:30:00'],
    ]);
    $this->upcoming = maintainedMonitor($this->organization, 'b upcoming', [
        ['Over', '2027-03-09 10:00:00', '2027-03-09 11:00:00'],
        ['Next', '2027-03-10 18:00:00', '2027-03-10 19:00:00'],
    ]);
    $this->free = maintainedMonitor($this->organization, 'c free', [
        ['Over', '2027-03-10 10:00:00', '2027-03-10 12:00:00'],
    ]);
    $this->actingAs(Members::seat($this->organization, Role::Viewer));
});

it('gives each monitor its active window, else its next one, else nothing', function (): void {
    $this->getJson("/api/v1/organizations/{$this->organization->id}/monitors")
        ->assertOk()
        ->assertJsonPath('data.0.maintenance', ['id' => MaintenanceWindow::query()->where('name', 'Now')->value('id'), 'name' => 'Now', 'starts_at' => '2027-03-10T11:30:00Z', 'ends_at' => '2027-03-10T12:30:00Z'])
        ->assertJsonPath('data.1.maintenance.name', 'Next')
        ->assertJsonPath('data.2.maintenance', null);
});

it('gives the window of a monitor in its detail', function (): void {
    $this->getJson("/api/v1/organizations/{$this->organization->id}/monitors/{$this->upcoming->id}")
        ->assertOk()
        ->assertJsonPath('data.maintenance.name', 'Next')
        ->assertJsonPath('data.maintenance.starts_at', '2027-03-10T18:00:00Z');
});
