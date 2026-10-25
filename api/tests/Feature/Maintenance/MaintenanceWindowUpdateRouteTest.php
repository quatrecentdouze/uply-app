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

function windowUrl(MaintenanceWindow $window): string
{
    return "/api/v1/organizations/{$window->organization_id}/maintenance-windows/{$window->id}";
}

function windowFor(Organization $organization, string $startsAt, string $endsAt, array $monitors): MaintenanceWindow
{
    $window = MaintenanceWindow::factory()->between($startsAt, $endsAt)->create(['organization_id' => $organization->id, 'name' => 'Upgrade']);
    foreach ($monitors as $monitor) {
        MaintenanceWindowMonitor::query()->create(['maintenance_window_id' => $window->id, 'monitor_id' => $monitor->id]);
    }

    return $window;
}

beforeEach(function (): void {
    freezeClock('2027-03-10 12:00:00');
    Event::fake([MaintenanceChangedEvent::class]);
    $this->organization = Organization::factory()->create();
    $this->api = Monitor::factory()->create(['organization_id' => $this->organization->id]);
    $this->site = Monitor::factory()->create(['organization_id' => $this->organization->id]);
    $this->upcoming = windowFor($this->organization, '2027-03-10 14:00:00', '2027-03-10 15:00:00', [$this->api]);
    $this->active = windowFor($this->organization, '2027-03-10 11:00:00', '2027-03-10 13:00:00', [$this->api]);
    $this->ended = windowFor($this->organization, '2027-03-10 09:00:00', '2027-03-10 10:00:00', [$this->api]);
    $this->actingAs(Members::seat($this->organization, Role::Member));
});

it('changes the name, the period and the monitors of an upcoming window and announces both monitor lists', function (): void {
    $this->patchJson(windowUrl($this->upcoming), ['name' => 'Longer upgrade', 'starts_at' => '2027-03-10T13:30:00Z', 'ends_at' => '2027-03-10T16:00:00Z', 'monitor_ids' => [$this->site->id]])
        ->assertOk()
        ->assertJsonPath('data.name', 'Longer upgrade')
        ->assertJsonPath('data.starts_at', '2027-03-10T13:30:00Z')
        ->assertJsonPath('data.ends_at', '2027-03-10T16:00:00Z')
        ->assertJsonPath('data.monitor_ids', [$this->site->id])
        ->assertJsonPath('data.state', 'upcoming');

    Event::assertDispatched(MaintenanceChangedEvent::class, fn (MaintenanceChangedEvent $event): bool => $event->windowId === $this->upcoming->id
        && $event->change === MaintenanceChange::Updated
        && $event->monitorIds === collect([$this->api->id, $this->site->id])->sort()->values()->all());
});

it('moves the end of an active window and keeps its start when the same start comes back', function (): void {
    $this->patchJson(windowUrl($this->active), ['starts_at' => '2027-03-10T11:00:00Z', 'ends_at' => '2027-03-10T12:30:00Z'])
        ->assertOk()
        ->assertJsonPath('data.starts_at', '2027-03-10T11:00:00Z')
        ->assertJsonPath('data.ends_at', '2027-03-10T12:30:00Z')
        ->assertJsonPath('data.state', 'active');
});

it('announces nothing when nothing changes', function (): void {
    $this->patchJson(windowUrl($this->upcoming), ['name' => 'Upgrade', 'monitor_ids' => [$this->api->id]])->assertOk();

    Event::assertNotDispatched(MaintenanceChangedEvent::class);
});

it('refuses to move the start of a started window', function (): void {
    $this->patchJson(windowUrl($this->active), ['starts_at' => '2027-03-10T11:30:00Z'])
        ->assertConflict()
        ->assertJsonPath('code', 'maintenance_started');

    expect($this->active->fresh()?->startsAt()->toTimeString())->toBe('11:00:00');
});

it('refuses any change of an ended window', function (array $payload): void {
    $this->patchJson(windowUrl($this->ended), $payload)
        ->assertConflict()
        ->assertJsonPath('code', 'maintenance_ended');
})->with([
    'name' => [['name' => 'Renamed']],
    'end' => [['ends_at' => '2027-03-10T18:00:00Z']],
    'start' => [['starts_at' => '2027-03-10T15:00:00Z', 'ends_at' => '2027-03-10T18:00:00Z']],
]);

it('validates the new period against the stored one', function (string $window, array $payload, string $field): void {
    $this->patchJson(windowUrl($this->{$window}), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'end before the stored start' => ['upcoming', ['ends_at' => '2027-03-10T13:00:00Z'], 'ends_at'],
    'start after the stored end' => ['upcoming', ['starts_at' => '2027-03-10T15:30:00Z'], 'ends_at'],
    'start in the past' => ['upcoming', ['starts_at' => '2027-03-10T11:00:00Z'], 'starts_at'],
    'end of an active window in the past' => ['active', ['ends_at' => '2027-03-10T11:30:00Z'], 'ends_at'],
    'active window longer than 7 days' => ['active', ['ends_at' => '2027-03-17T11:00:01Z'], 'ends_at'],
    'empty name' => ['upcoming', ['name' => ''], 'name'],
    'no monitor' => ['upcoming', ['monitor_ids' => []], 'monitor_ids'],
]);

it('refuses a viewer and hides the windows of another organization', function (): void {
    $foreign = MaintenanceWindow::factory()->create();

    $this->actingAs(Members::seat($this->organization, Role::Viewer))->patchJson(windowUrl($this->upcoming), ['name' => 'No'])->assertForbidden();
    $this->actingAs(Members::seat($this->organization, Role::Owner))
        ->patchJson("/api/v1/organizations/{$this->organization->id}/maintenance-windows/{$foreign->id}", ['name' => 'No'])
        ->assertNotFound();
});
