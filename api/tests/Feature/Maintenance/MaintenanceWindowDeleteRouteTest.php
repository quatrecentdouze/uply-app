<?php

declare(strict_types=1);

use App\Maintenance\Enums\MaintenanceChange;
use App\Maintenance\Events\MaintenanceChangedEvent;
use App\Maintenance\Models\MaintenanceWindow;
use App\Maintenance\Models\MaintenanceWindowMonitor;
use App\Monitoring\Models\Monitor;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use Illuminate\Support\Facades\Event;
use Tests\Support\Members;

function deletableWindow(Organization $organization, Monitor $monitor, string $startsAt, string $endsAt): MaintenanceWindow
{
    $window = MaintenanceWindow::factory()->between($startsAt, $endsAt)->create(['organization_id' => $organization->id]);
    MaintenanceWindowMonitor::query()->create(['maintenance_window_id' => $window->id, 'monitor_id' => $monitor->id]);

    return $window;
}

function deleteWindowUrl(MaintenanceWindow $window): string
{
    return "/api/v1/organizations/{$window->organization_id}/maintenance-windows/{$window->id}";
}

beforeEach(function (): void {
    freezeClock('2027-03-10 12:00:00');
    Event::fake([MaintenanceChangedEvent::class]);
    $this->organization = Organization::factory()->create();
    $this->monitor = Monitor::factory()->create(['organization_id' => $this->organization->id]);
});

it('deletes an upcoming window with its monitors and announces it', function (Role $role): void {
    $window = deletableWindow($this->organization, $this->monitor, '2027-03-10 13:00:00', '2027-03-10 14:00:00');

    $this->actingAs(Members::seat($this->organization, $role))->deleteJson(deleteWindowUrl($window))->assertNoContent();

    expect(MaintenanceWindow::query()->count())->toBe(0)
        ->and(MaintenanceWindowMonitor::query()->count())->toBe(0);
    Event::assertDispatched(MaintenanceChangedEvent::class, fn (MaintenanceChangedEvent $event): bool => $event->windowId === $window->id
        && $event->change === MaintenanceChange::Deleted
        && $event->monitorIds === [$this->monitor->id]);
})->with([Role::Owner, Role::Admin, Role::Member]);

it('keeps a started window as history', function (string $startsAt, string $endsAt): void {
    $window = deletableWindow($this->organization, $this->monitor, $startsAt, $endsAt);

    $this->actingAs(Members::seat($this->organization, Role::Owner))
        ->deleteJson(deleteWindowUrl($window))
        ->assertConflict()
        ->assertJsonPath('code', 'maintenance_started');

    expect(MaintenanceWindow::query()->count())->toBe(1);
    Event::assertNotDispatched(MaintenanceChangedEvent::class);
})->with([
    'active' => ['2027-03-10 11:00:00', '2027-03-10 14:00:00'],
    'ended' => ['2027-03-10 10:00:00', '2027-03-10 11:00:00'],
]);

it('refuses a viewer and hides the windows of another organization', function (): void {
    $window = deletableWindow($this->organization, $this->monitor, '2027-03-10 13:00:00', '2027-03-10 14:00:00');
    $foreign = MaintenanceWindow::factory()->between('2027-03-10 13:00:00', '2027-03-10 14:00:00')->create();

    $this->actingAs(Members::seat($this->organization, Role::Viewer))->deleteJson(deleteWindowUrl($window))->assertForbidden();
    $this->actingAs(Members::seat($this->organization, Role::Owner))
        ->deleteJson("/api/v1/organizations/{$this->organization->id}/maintenance-windows/{$foreign->id}")
        ->assertNotFound();

    expect(MaintenanceWindow::query()->count())->toBe(2);
});
