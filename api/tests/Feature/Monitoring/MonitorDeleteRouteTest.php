<?php

declare(strict_types=1);

use App\Alerting\Models\AlertChannel;
use App\Incidents\Models\Incident;
use App\Monitoring\Enums\MonitorChange;
use App\Monitoring\Events\MonitorChangedEvent;
use App\Monitoring\Models\Monitor;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\CheckHistory;
use Tests\Support\Members;

function deleteMonitorUrl(Monitor $monitor): string
{
    return "/api/v1/organizations/{$monitor->organization_id}/monitors/{$monitor->id}";
}

beforeEach(function (): void {
    $this->clock = freezeClock('2027-03-10 12:00:00');
    CheckHistory::keepDaysBefore($this->clock->now(), 0);
    $this->organization = Organization::factory()->create();
    $this->monitor = Monitor::factory()->state(['organization_id' => $this->organization->id])->create();
});

it('deletes a monitor with its history for the roles that manage monitors', function (Role $role): void {
    Event::fake([MonitorChangedEvent::class]);
    CheckHistory::result($this->monitor->id, $this->clock->now()->subMinute(), up: true);
    CheckHistory::hour($this->monitor->id, $this->clock->now()->startOfHour(), 1, 1);
    Incident::factory()->create(['monitor_id' => $this->monitor->id]);
    $channel = AlertChannel::factory()->create(['organization_id' => $this->organization->id]);
    DB::table('alert_channel_monitor')->insert(['alert_channel_id' => $channel->id, 'monitor_id' => $this->monitor->id]);

    $this->actingAs(Members::seat($this->organization, $role))->deleteJson(deleteMonitorUrl($this->monitor))->assertNoContent();

    expect(Monitor::query()->count())->toBe(0)
        ->and(DB::table('check_results')->count())->toBe(0)
        ->and(DB::table('monitor_hourly_stats')->count())->toBe(0)
        ->and(Incident::query()->count())->toBe(0)
        ->and(DB::table('alert_channel_monitor')->count())->toBe(0)
        ->and(AlertChannel::query()->count())->toBe(1);
    Event::assertDispatched(MonitorChangedEvent::class, fn (MonitorChangedEvent $event): bool => $event->monitorId === $this->monitor->id
        && $event->organizationId === $this->organization->id
        && $event->change === MonitorChange::Deleted);
})->with([Role::Owner, Role::Admin, Role::Member]);

it('refuses a viewer, a non member and a monitor of another organization', function (): void {
    $foreign = Monitor::factory()->create();

    $this->actingAs(Members::seat($this->organization, Role::Viewer))->deleteJson(deleteMonitorUrl($this->monitor))->assertForbidden();
    $this->actingAs(Members::outsider())->deleteJson(deleteMonitorUrl($this->monitor))->assertNotFound();
    $this->actingAs(Members::seat($this->organization, Role::Owner))
        ->deleteJson("/api/v1/organizations/{$this->organization->id}/monitors/{$foreign->id}")
        ->assertNotFound();

    expect(Monitor::query()->count())->toBe(2);
});
