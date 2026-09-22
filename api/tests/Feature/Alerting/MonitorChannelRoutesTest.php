<?php

declare(strict_types=1);

use App\Alerting\Models\AlertChannel;
use App\Monitoring\Models\Monitor;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use Illuminate\Support\Facades\DB;
use Tests\Support\Members;

function monitorChannelsUrl(Monitor $monitor): string
{
    return "/api/v1/organizations/{$monitor->organization_id}/monitors/{$monitor->id}/alert-channels";
}

function linkedChannelIds(Monitor $monitor): array
{
    return DB::table('alert_channel_monitor')->where('monitor_id', $monitor->id)->orderBy('alert_channel_id')->pluck('alert_channel_id')->all();
}

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $inOrganization = ['organization_id' => $this->organization->id];
    $this->monitor = Monitor::factory()->state($inOrganization)->create();
    $this->mail = AlertChannel::factory()->state($inOrganization)->create(['name' => 'Mail']);
    $this->slack = AlertChannel::factory()->state($inOrganization)->slack()->create(['name' => 'Slack']);
    DB::table('alert_channel_monitor')->insert(['alert_channel_id' => $this->mail->id, 'monitor_id' => $this->monitor->id]);
});

it('lists the channels linked to a monitor to every role', function (Role $role): void {
    $this->actingAs(Members::seat($this->organization, $role))->getJson(monitorChannelsUrl($this->monitor))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->mail->id)
        ->assertJsonPath('data.0.name', 'Mail');
})->with([Role::Owner, Role::Admin, Role::Member, Role::Viewer]);

it('replaces the linked channels for the roles that manage monitors', function (Role $role): void {
    $this->actingAs(Members::seat($this->organization, $role))
        ->putJson(monitorChannelsUrl($this->monitor), ['channel_ids' => [$this->slack->id]])
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->slack->id);

    expect(linkedChannelIds($this->monitor))->toBe([$this->slack->id]);
})->with([Role::Owner, Role::Admin, Role::Member]);

it('unlinks every channel with an empty list', function (): void {
    $this->actingAs(Members::seat($this->organization, Role::Member))
        ->putJson(monitorChannelsUrl($this->monitor), ['channel_ids' => []])
        ->assertOk()
        ->assertJsonCount(0, 'data');

    expect(linkedChannelIds($this->monitor))->toBe([]);
});

it('only links channels of the same organization', function (): void {
    $foreign = AlertChannel::factory()->create();

    $this->actingAs(Members::seat($this->organization, Role::Owner))
        ->putJson(monitorChannelsUrl($this->monitor), ['channel_ids' => [$this->slack->id, $foreign->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('channel_ids.1');

    expect(linkedChannelIds($this->monitor))->toBe([$this->mail->id]);
});

it('validates the list', function (array $payload, string $field): void {
    $this->actingAs(Members::seat($this->organization, Role::Owner))
        ->putJson(monitorChannelsUrl($this->monitor), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'no list' => [[], 'channel_ids'],
    'not a list' => [['channel_ids' => 'all'], 'channel_ids'],
    'not an id' => [['channel_ids' => ['mail']], 'channel_ids.0'],
]);

it('refuses a viewer, a non member and a monitor of another organization', function (): void {
    $foreignMonitor = Monitor::factory()->create();

    $this->actingAs(Members::seat($this->organization, Role::Viewer))
        ->putJson(monitorChannelsUrl($this->monitor), ['channel_ids' => []])
        ->assertForbidden();
    $this->actingAs(Members::outsider())->getJson(monitorChannelsUrl($this->monitor))->assertNotFound();
    $this->actingAs(Members::seat($this->organization, Role::Owner))
        ->getJson("/api/v1/organizations/{$this->organization->id}/monitors/{$foreignMonitor->id}/alert-channels")
        ->assertNotFound();
    $this->putJson("/api/v1/organizations/{$this->organization->id}/monitors/{$foreignMonitor->id}/alert-channels", ['channel_ids' => [$this->slack->id]])
        ->assertNotFound();

    expect(linkedChannelIds($foreignMonitor))->toBe([]);
});
