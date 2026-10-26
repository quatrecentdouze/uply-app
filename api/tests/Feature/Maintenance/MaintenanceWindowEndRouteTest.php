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

function endWindowUrl(MaintenanceWindow $window): string
{
    return "/api/v1/organizations/{$window->organization_id}/maintenance-windows/{$window->id}/end";
}

beforeEach(function (): void {
    $this->clock = freezeClock('2027-03-10 12:00:00');
    Event::fake([MaintenanceChangedEvent::class]);
    $this->organization = Organization::factory()->create();
    $this->monitor = Monitor::factory()->create(['organization_id' => $this->organization->id]);
});

function windowOf(Organization $organization, Monitor $monitor, string $startsAt, string $endsAt): MaintenanceWindow
{
    $window = MaintenanceWindow::factory()->between($startsAt, $endsAt)->create(['organization_id' => $organization->id]);
    MaintenanceWindowMonitor::query()->create(['maintenance_window_id' => $window->id, 'monitor_id' => $monitor->id]);

    return $window;
}

it('ends an active window now on the server clock and announces it', function (Role $role): void {
    $window = windowOf($this->organization, $this->monitor, '2027-03-10 11:00:00', '2027-03-10 14:00:00');
    $this->clock->moveTo($this->clock->now()->addMilliseconds(400));

    $this->actingAs(Members::seat($this->organization, $role))
        ->postJson(endWindowUrl($window))
        ->assertOk()
        ->assertJsonPath('data.ends_at', '2027-03-10T12:00:00Z')
        ->assertJsonPath('data.state', 'ended');

    Event::assertDispatched(MaintenanceChangedEvent::class, fn (MaintenanceChangedEvent $event): bool => $event->windowId === $window->id
        && $event->change === MaintenanceChange::Ended
        && $event->monitorIds === [$this->monitor->id]);
})->with([Role::Owner, Role::Admin, Role::Member]);

it('ends a window that started this very second one second after its start', function (): void {
    $window = windowOf($this->organization, $this->monitor, '2027-03-10 12:00:00', '2027-03-10 14:00:00');

    $this->actingAs(Members::seat($this->organization, Role::Member))
        ->postJson(endWindowUrl($window))
        ->assertOk()
        ->assertJsonPath('data.ends_at', '2027-03-10T12:00:01Z');
});

it('refuses to end a window that is not active', function (string $startsAt, string $endsAt): void {
    $window = windowOf($this->organization, $this->monitor, $startsAt, $endsAt);

    $this->actingAs(Members::seat($this->organization, Role::Member))
        ->postJson(endWindowUrl($window))
        ->assertConflict()
        ->assertJsonPath('code', 'maintenance_not_active');

    expect($window->fresh()?->endsAt()->toDateTimeString())->toBe($endsAt);
    Event::assertNotDispatched(MaintenanceChangedEvent::class);
})->with([
    'upcoming' => ['2027-03-10 13:00:00', '2027-03-10 14:00:00'],
    'ended' => ['2027-03-10 10:00:00', '2027-03-10 11:00:00'],
]);

it('refuses a viewer and hides the windows of another organization', function (): void {
    $window = windowOf($this->organization, $this->monitor, '2027-03-10 11:00:00', '2027-03-10 14:00:00');
    $foreign = MaintenanceWindow::factory()->between('2027-03-10 11:00:00', '2027-03-10 14:00:00')->create();

    $this->actingAs(Members::seat($this->organization, Role::Viewer))->postJson(endWindowUrl($window))->assertForbidden();
    $this->actingAs(Members::seat($this->organization, Role::Owner))
        ->postJson("/api/v1/organizations/{$this->organization->id}/maintenance-windows/{$foreign->id}/end")
        ->assertNotFound();
});
