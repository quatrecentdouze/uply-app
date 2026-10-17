<?php

declare(strict_types=1);

use App\Incidents\Models\Incident;
use App\Monitoring\Enums\MonitorChange;
use App\Monitoring\Enums\MonitorState;
use App\Monitoring\Events\MonitorChangedEvent;
use App\Monitoring\Models\Monitor;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use Illuminate\Support\Facades\Event;
use Tests\Support\Members;

function updateMonitorUrl(Monitor $monitor): string
{
    return "/api/v1/organizations/{$monitor->organization_id}/monitors/{$monitor->id}";
}

beforeEach(function (): void {
    $this->clock = freezeClock('2027-03-10 12:00:00');
    $this->organization = Organization::factory()->create();
    $this->monitor = Monitor::factory()->state(['organization_id' => $this->organization->id])->create([
        'name' => 'API',
        'url' => 'https://api.example.com/health',
        'interval_seconds' => 300,
        'next_check_at' => $this->clock->now()->addSeconds(240),
    ]);
    $this->monitor->forceFill([
        'state' => MonitorState::Down,
        'consecutive_failures' => 1,
        'consecutive_successes' => 0,
        'streak_started_at' => $this->clock->now()->subMinutes(3),
        'state_changed_at' => $this->clock->now()->subMinutes(10),
    ])->save();
    $this->incident = Incident::factory()->create(['monitor_id' => $this->monitor->id]);
});

function changed(Monitor $monitor): Monitor
{
    return $monitor->fresh() ?? throw new LogicException('the monitor is gone');
}

it('lets the roles that manage monitors rename one', function (Role $role): void {
    Event::fake([MonitorChangedEvent::class]);

    $this->actingAs(Members::seat($this->organization, $role))
        ->patchJson(updateMonitorUrl($this->monitor), ['name' => 'Public API'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Public API')
        ->assertJsonPath('data.state', 'down');

    Event::assertDispatched(MonitorChangedEvent::class, fn (MonitorChangedEvent $event): bool => $event->monitorId === $this->monitor->id
        && $event->organizationId === $this->organization->id
        && $event->change === MonitorChange::Updated);
})->with([Role::Owner, Role::Admin, Role::Member]);

it('refuses a viewer, a non member and a monitor of another organization', function (): void {
    $foreign = Monitor::factory()->create();

    $this->actingAs(Members::seat($this->organization, Role::Viewer))->patchJson(updateMonitorUrl($this->monitor), ['name' => 'X'])->assertForbidden();
    $this->actingAs(Members::outsider())->patchJson(updateMonitorUrl($this->monitor), ['name' => 'X'])->assertNotFound();
    $this->actingAs(Members::seat($this->organization, Role::Owner))
        ->patchJson("/api/v1/organizations/{$this->organization->id}/monitors/{$foreign->id}", ['name' => 'X'])
        ->assertNotFound();

    expect(changed($this->monitor)->name)->toBe('API')
        ->and($foreign->fresh()?->name)->not->toBe('X');
});

it('keeps the streak and the schedule on a rename', function (): void {
    $this->actingAs(Members::seat($this->organization, Role::Member))
        ->patchJson(updateMonitorUrl($this->monitor), ['name' => 'Renamed', 'timeout_seconds' => 20])
        ->assertOk();

    $monitor = changed($this->monitor);
    expect($monitor->consecutive_failures)->toBe(1)
        ->and($monitor->timeout_seconds)->toBe(20)
        ->and($monitor->nextCheckAt()->toIso8601ZuluString())->toBe('2027-03-10T12:04:00Z');
});

it('checks at once and clears the counters when what is judged changes', function (array $changes): void {
    $this->actingAs(Members::seat($this->organization, Role::Member))
        ->patchJson(updateMonitorUrl($this->monitor), $changes)
        ->assertOk()
        ->assertJsonPath('data.state', 'down');

    $monitor = changed($this->monitor);
    expect($monitor->consecutive_failures)->toBe(0)
        ->and($monitor->consecutive_successes)->toBe(0)
        ->and($monitor->streak_started_at)->toBeNull()
        ->and($monitor->state)->toBe(MonitorState::Down)
        ->and($monitor->nextCheckAt()->toIso8601ZuluString())->toBe('2027-03-10T12:00:00Z')
        ->and($this->incident->fresh()?->resolved_at)->toBeNull();
})->with([
    'url' => [['url' => 'https://api.example.com/ready']],
    'method' => [['method' => 'head']],
    'accepted codes' => [['accepted_status_codes' => ['200-299', '503']]],
    'redirects' => [['follow_redirects' => false]],
]);

it('brings the next check closer when the interval gets shorter', function (): void {
    $this->actingAs(Members::seat($this->organization, Role::Member))
        ->patchJson(updateMonitorUrl($this->monitor), ['interval_seconds' => 60])
        ->assertOk()
        ->assertJsonPath('data.interval_seconds', 60);

    expect(changed($this->monitor)->nextCheckAt()->toIso8601ZuluString())->toBe('2027-03-10T12:01:00Z')
        ->and(changed($this->monitor)->consecutive_failures)->toBe(1);
});

it('pauses with the state and the open incident kept, then resumes with a check at once', function (): void {
    $this->actingAs(Members::seat($this->organization, Role::Member));

    $this->patchJson(updateMonitorUrl($this->monitor), ['is_paused' => true])
        ->assertOk()
        ->assertJsonPath('data.is_paused', true)
        ->assertJsonPath('data.state', 'down');
    expect(changed($this->monitor)->consecutive_failures)->toBe(1)
        ->and($this->incident->fresh()?->resolved_at)->toBeNull();

    $this->clock->advance(3600);
    $this->patchJson(updateMonitorUrl($this->monitor), ['is_paused' => false])
        ->assertOk()
        ->assertJsonPath('data.is_paused', false);
    expect(changed($this->monitor)->nextCheckAt()->toIso8601ZuluString())->toBe('2027-03-10T13:00:00Z');
});

it('emits nothing when nothing changes', function (): void {
    Event::fake([MonitorChangedEvent::class]);

    $this->actingAs(Members::seat($this->organization, Role::Member))
        ->patchJson(updateMonitorUrl($this->monitor), ['name' => 'API', 'interval_seconds' => 300])
        ->assertOk();

    Event::assertNotDispatched(MonitorChangedEvent::class);
});

it('validates the changed fields only', function (array $payload, string $field): void {
    $this->actingAs(Members::seat($this->organization, Role::Admin))
        ->patchJson(updateMonitorUrl($this->monitor), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'empty name' => [['name' => ''], 'name'],
    'blocked url' => [['url' => 'http://169.254.169.254/'], 'url'],
    'interval off the list' => [['interval_seconds' => 7], 'interval_seconds'],
    'pause as text' => [['is_paused' => 'soon'], 'is_paused'],
    'failure threshold zero' => [['failure_threshold' => 0], 'failure_threshold'],
    'recovery threshold eleven' => [['recovery_threshold' => 11], 'recovery_threshold'],
]);

it('keeps the state, the counters and the schedule when a threshold changes', function (): void {
    Event::fake([MonitorChangedEvent::class]);

    $this->actingAs(Members::seat($this->organization, Role::Member))
        ->patchJson(updateMonitorUrl($this->monitor), ['failure_threshold' => 5, 'recovery_threshold' => 1])
        ->assertOk()
        ->assertJsonPath('data.failure_threshold', 5)
        ->assertJsonPath('data.recovery_threshold', 1)
        ->assertJsonPath('data.state', 'down');

    $monitor = changed($this->monitor);
    expect($monitor->consecutive_failures)->toBe(1)
        ->and($monitor->streak_started_at?->toIso8601ZuluString())->toBe('2027-03-10T11:57:00Z')
        ->and($monitor->nextCheckAt()->toIso8601ZuluString())->toBe('2027-03-10T12:04:00Z');
    Event::assertDispatchedOnce(MonitorChangedEvent::class);
});
