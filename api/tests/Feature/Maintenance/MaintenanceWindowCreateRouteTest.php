<?php

declare(strict_types=1);

use App\Maintenance\Enums\MaintenanceChange;
use App\Maintenance\Events\MaintenanceChangedEvent;
use App\Maintenance\Models\MaintenanceWindow;
use App\Maintenance\Settings\MaintenanceSettings;
use App\Monitoring\Models\Monitor;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use Illuminate\Support\Facades\Event;
use Tests\Support\Members;

function newWindowUrl(Organization $organization): string
{
    return "/api/v1/organizations/{$organization->id}/maintenance-windows";
}

beforeEach(function (): void {
    freezeClock('2027-03-10 12:00:00');
    $this->organization = Organization::factory()->create();
    $this->api = Monitor::factory()->create(['organization_id' => $this->organization->id]);
    $this->site = Monitor::factory()->create(['organization_id' => $this->organization->id]);
    $this->payload = ['name' => 'Database upgrade', 'starts_at' => '2027-03-10T14:00:00Z', 'ends_at' => '2027-03-10T15:30:00Z', 'monitor_ids' => [$this->site->id, $this->api->id]];
});

it('schedules a window for the roles that manage monitors and announces it', function (Role $role): void {
    Event::fake([MaintenanceChangedEvent::class]);
    $author = Members::seat($this->organization, $role);

    $id = $this->actingAs($author)
        ->postJson(newWindowUrl($this->organization), $this->payload)
        ->assertCreated()
        ->assertJsonPath('data.name', 'Database upgrade')
        ->assertJsonPath('data.starts_at', '2027-03-10T14:00:00Z')
        ->assertJsonPath('data.ends_at', '2027-03-10T15:30:00Z')
        ->assertJsonPath('data.state', 'upcoming')
        ->assertJsonPath('data.created_at', '2027-03-10T12:00:00Z')
        ->json('data.id');

    $window = MaintenanceWindow::query()->sole();
    expect($window->id)->toBe($id)
        ->and($window->organization_id)->toBe($this->organization->id)
        ->and($window->created_by_user_id)->toBe($author->id)
        ->and($window->monitorIds())->toEqualCanonicalizing([$this->api->id, $this->site->id]);
    Event::assertDispatched(MaintenanceChangedEvent::class, fn (MaintenanceChangedEvent $event): bool => $event->windowId === $id
        && $event->organizationId === $this->organization->id
        && $event->change === MaintenanceChange::Created
        && $event->monitorIds === collect([$this->api->id, $this->site->id])->sort()->values()->all());
})->with([Role::Owner, Role::Admin, Role::Member]);

it('starts a window now when no start is given', function (?string $startsAt): void {
    $payload = ['name' => 'Hotfix', 'ends_at' => '2027-03-10T12:30:00Z', 'monitor_ids' => [$this->api->id]];

    $this->actingAs(Members::seat($this->organization, Role::Member))
        ->postJson(newWindowUrl($this->organization), $startsAt === null ? $payload : [...$payload, 'starts_at' => null])
        ->assertCreated()
        ->assertJsonPath('data.starts_at', '2027-03-10T12:00:00Z')
        ->assertJsonPath('data.state', 'active');
})->with([null, 'sent as null']);

it('keeps whole seconds in utc', function (): void {
    $this->actingAs(Members::seat($this->organization, Role::Member))
        ->postJson(newWindowUrl($this->organization), [...$this->payload, 'starts_at' => '2027-03-10T15:00:00.750+01:00', 'ends_at' => '2027-03-10T16:00:00+01:00'])
        ->assertCreated()
        ->assertJsonPath('data.starts_at', '2027-03-10T14:00:00Z')
        ->assertJsonPath('data.ends_at', '2027-03-10T15:00:00Z');
});

it('refuses a viewer and a non member', function (): void {
    $this->actingAs(Members::seat($this->organization, Role::Viewer))->postJson(newWindowUrl($this->organization), $this->payload)->assertForbidden();
    $this->actingAs(Members::outsider())->postJson(newWindowUrl($this->organization), $this->payload)->assertNotFound();

    expect(MaintenanceWindow::query()->count())->toBe(0);
});

it('validates the window', function (array $override, string $field): void {
    $foreign = Monitor::factory()->create();
    $override = array_map(fn (mixed $value): mixed => $value === 'foreign monitor' ? [$foreign->id] : $value, $override);

    $this->actingAs(Members::seat($this->organization, Role::Owner))
        ->postJson(newWindowUrl($this->organization), [...$this->payload, ...$override])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'no name' => [['name' => ''], 'name'],
    'long name' => [['name' => str_repeat('n', 101)], 'name'],
    'no end' => [['ends_at' => null], 'ends_at'],
    'end that is no date' => [['ends_at' => 'soon'], 'ends_at'],
    'start that is no date' => [['starts_at' => 'later'], 'starts_at'],
    'start two minutes ago' => [['starts_at' => '2027-03-10T11:58:00Z'], 'starts_at'],
    'start beyond 90 days' => [['starts_at' => '2027-06-09T12:00:00Z', 'ends_at' => '2027-06-09T13:00:00Z'], 'starts_at'],
    'end before the start' => [['ends_at' => '2027-03-10T13:00:00Z'], 'ends_at'],
    'end now' => [['starts_at' => '2027-03-10T11:59:30Z', 'ends_at' => '2027-03-10T12:00:00Z'], 'ends_at'],
    'longer than 7 days' => [['ends_at' => '2027-03-17T14:00:01Z'], 'ends_at'],
    'no monitor' => [['monitor_ids' => []], 'monitor_ids'],
    'monitors as text' => [['monitor_ids' => 'all'], 'monitor_ids'],
    'monitor of another organization' => [['monitor_ids' => 'foreign monitor'], 'monitor_ids.0'],
    'monitor that is no id' => [['monitor_ids' => ['api']], 'monitor_ids.0'],
]);

it('refuses the same monitor twice and more monitors than a window holds', function (): void {
    $this->actingAs(Members::seat($this->organization, Role::Owner));

    $this->postJson(newWindowUrl($this->organization), [...$this->payload, 'monitor_ids' => [$this->api->id, strtoupper($this->api->id)]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('monitor_ids.1');
    config(['maintenance.limits.max_monitors_per_window' => 1]);
    app()->forgetInstance(MaintenanceSettings::class);
    $this->postJson(newWindowUrl($this->organization), $this->payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('monitor_ids');
});

it('refuses a window over the limit of upcoming or active windows', function (): void {
    config(['maintenance.limits.max_open_windows_per_organization' => 2]);
    app()->forgetInstance(MaintenanceSettings::class);
    MaintenanceWindow::factory()->between('2027-03-09 10:00:00', '2027-03-09 11:00:00')->create(['organization_id' => $this->organization->id]);
    MaintenanceWindow::factory()->between('2027-03-10 11:00:00', '2027-03-10 13:00:00')->create(['organization_id' => $this->organization->id]);
    MaintenanceWindow::factory()->between('2027-03-11 11:00:00', '2027-03-11 13:00:00')->create(['organization_id' => $this->organization->id]);
    MaintenanceWindow::factory()->between('2027-03-11 11:00:00', '2027-03-11 13:00:00')->create();

    $this->actingAs(Members::seat($this->organization, Role::Owner))
        ->postJson(newWindowUrl($this->organization), $this->payload)
        ->assertConflict()
        ->assertExactJson(['code' => 'maintenance_limit_reached', 'message' => 'This organization already has the most upcoming or active maintenance windows allowed (2).']);
});
