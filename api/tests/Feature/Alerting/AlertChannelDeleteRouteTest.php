<?php

declare(strict_types=1);

use App\Alerting\Models\AlertChannel;
use App\Alerting\Models\AlertDelivery;
use App\Monitoring\Models\Monitor;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use Illuminate\Support\Facades\DB;
use Tests\Support\Members;

function deleteChannelUrl(AlertChannel $channel): string
{
    return "/api/v1/organizations/{$channel->organization_id}/alert-channels/{$channel->id}";
}

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->channel = AlertChannel::factory()->create(['organization_id' => $this->organization->id]);
    $this->monitor = Monitor::factory()->create(['organization_id' => $this->organization->id]);
    DB::table('alert_channel_monitor')->insert(['alert_channel_id' => $this->channel->id, 'monitor_id' => $this->monitor->id]);
    AlertDelivery::query()->create(['alert_channel_id' => $this->channel->id, 'incident_id' => null, 'kind' => 'test', 'status' => 'sent', 'attempts' => 1]);
});

it('deletes a channel with its links and deliveries for the roles that manage channels', function (Role $role): void {
    $this->actingAs(Members::seat($this->organization, $role))->deleteJson(deleteChannelUrl($this->channel))->assertNoContent();

    expect(AlertChannel::query()->count())->toBe(0)
        ->and(DB::table('alert_channel_monitor')->count())->toBe(0)
        ->and(AlertDelivery::query()->count())->toBe(0)
        ->and(Monitor::query()->count())->toBe(1);
})->with([Role::Owner, Role::Admin]);

it('refuses the roles that do not manage channels and the channels of another organization', function (): void {
    $foreign = AlertChannel::factory()->create();

    $this->actingAs(Members::seat($this->organization, Role::Member))->deleteJson(deleteChannelUrl($this->channel))->assertForbidden();
    $this->actingAs(Members::seat($this->organization, Role::Viewer))->deleteJson(deleteChannelUrl($this->channel))->assertForbidden();
    $this->actingAs(Members::outsider())->deleteJson(deleteChannelUrl($this->channel))->assertNotFound();
    $this->actingAs(Members::seat($this->organization, Role::Owner))
        ->deleteJson("/api/v1/organizations/{$this->organization->id}/alert-channels/{$foreign->id}")
        ->assertNotFound();

    expect(AlertChannel::query()->count())->toBe(2);
});
