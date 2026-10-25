<?php

declare(strict_types=1);

use App\Maintenance\Models\MaintenanceWindow;
use App\Maintenance\Models\MaintenanceWindowMonitor;
use App\Monitoring\Models\Monitor;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use Tests\Support\Members;

function maintenanceWindowsUrl(Organization $organization, ?string $windowId = null): string
{
    return "/api/v1/organizations/{$organization->id}/maintenance-windows".($windowId === null ? '' : "/{$windowId}");
}

function organizationWindow(Organization $organization, string $name, string $startsAt, string $endsAt, array $monitors = []): MaintenanceWindow
{
    $window = MaintenanceWindow::factory()->between($startsAt, $endsAt)->create(['organization_id' => $organization->id, 'name' => $name]);
    foreach ($monitors as $monitor) {
        MaintenanceWindowMonitor::query()->create(['maintenance_window_id' => $window->id, 'monitor_id' => $monitor->id]);
    }

    return $window;
}

beforeEach(function (): void {
    freezeClock('2027-03-10 12:00:00');
    $this->organization = Organization::factory()->create();
    $this->monitor = Monitor::factory()->create(['organization_id' => $this->organization->id]);
    $this->old = organizationWindow($this->organization, 'Old', '2027-03-08 10:00:00', '2027-03-08 11:00:00');
    $this->older = organizationWindow($this->organization, 'Older', '2027-03-01 10:00:00', '2027-03-01 11:00:00');
    $this->later = organizationWindow($this->organization, 'Later', '2027-03-12 10:00:00', '2027-03-12 11:00:00');
    $this->soon = organizationWindow($this->organization, 'Soon', '2027-03-10 13:00:00', '2027-03-10 14:00:00');
    $this->now = organizationWindow($this->organization, 'Now', '2027-03-10 11:00:00', '2027-03-10 13:00:00', [$this->monitor]);
    organizationWindow(Organization::factory()->create(), 'Elsewhere', '2027-03-10 11:00:00', '2027-03-10 13:00:00');
});

it('shows a window with its state from the server clock and its monitors to every role', function (Role $role): void {
    $this->actingAs(Members::seat($this->organization, $role))
        ->getJson(maintenanceWindowsUrl($this->organization, $this->now->id))
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $this->now->id,
            'name' => 'Now',
            'starts_at' => '2027-03-10T11:00:00Z',
            'ends_at' => '2027-03-10T13:00:00Z',
            'state' => 'active',
            'monitor_ids' => [$this->monitor->id],
            'created_at' => '2027-03-10T12:00:00Z',
        ]]);
})->with([Role::Owner, Role::Admin, Role::Member, Role::Viewer]);

it('lists active and upcoming windows by start, then ended ones newest first', function (): void {
    $this->actingAs(Members::seat($this->organization, Role::Viewer))
        ->getJson(maintenanceWindowsUrl($this->organization))
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Now', 'Soon', 'Later', 'Old', 'Older'])
        ->assertJsonPath('data.*.state', ['active', 'upcoming', 'upcoming', 'ended', 'ended'])
        ->assertJsonPath('meta.total', 5);
});

it('filters the list by state', function (string $state, array $names): void {
    $this->actingAs(Members::seat($this->organization, Role::Member))
        ->getJson(maintenanceWindowsUrl($this->organization).'?state='.$state)
        ->assertOk()
        ->assertJsonPath('data.*.name', $names);
})->with([
    ['upcoming', ['Soon', 'Later']],
    ['active', ['Now']],
    ['ended', ['Old', 'Older']],
]);

it('pages the list and refuses an unknown state', function (): void {
    $this->actingAs(Members::seat($this->organization, Role::Member));

    $this->getJson(maintenanceWindowsUrl($this->organization).'?per_page=2&page=2')
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Later', 'Old']);
    $this->getJson(maintenanceWindowsUrl($this->organization).'?state=past')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('state');
});

it('hides the windows from a non member and the windows of another organization', function (): void {
    $foreign = MaintenanceWindow::query()->where('name', 'Elsewhere')->sole();

    $this->actingAs(Members::outsider())->getJson(maintenanceWindowsUrl($this->organization))->assertNotFound();
    $this->actingAs(Members::seat($this->organization, Role::Owner))->getJson(maintenanceWindowsUrl($this->organization, $foreign->id))->assertNotFound();
});
