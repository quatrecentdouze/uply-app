<?php

declare(strict_types=1);

use App\Alerting\Enums\DisabledReason;
use App\Alerting\Models\AlertChannel;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use Tests\Support\Members;

function channelsUrl(Organization $organization, ?string $channelId = null): string
{
    return "/api/v1/organizations/{$organization->id}/alert-channels".($channelId === null ? '' : "/{$channelId}");
}

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $inOrganization = ['organization_id' => $this->organization->id];
    $this->email = AlertChannel::factory()->state($inOrganization)->create(['name' => 'Ops mail', 'settings' => [AlertChannel::RECIPIENTS_SETTING => ['ops@example.com', 'cto@example.com']]]);
    $this->discord = AlertChannel::factory()->state($inOrganization)->discord('https://discord.com/api/webhooks/1234567890/discord-secret-token-AbCd')->create(['name' => 'Discord']);
    $this->slack = AlertChannel::factory()->state($inOrganization)->slack('https://hooks.slack.com/services/T000/B000/slack-secret-token-WxYz')->create([
        'name' => 'Slack',
        'is_enabled' => false,
        'disabled_reason' => DisabledReason::WebhookRejected,
    ]);
    AlertChannel::factory()->create(['name' => 'Elsewhere']);
});

it('lists the channels of the organization with hints instead of webhooks to every role', function (Role $role): void {
    $response = $this->actingAs(Members::seat($this->organization, $role))->getJson(channelsUrl($this->organization))
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('data.0.name', 'Discord')
        ->assertJsonPath('data.0.type', 'discord')
        ->assertJsonPath('data.0.is_enabled', true)
        ->assertJsonPath('data.0.disabled_reason', null)
        ->assertJsonPath('data.0.recipients', null)
        ->assertJsonPath('data.0.webhook_hint', ['host' => 'discord.com', 'last_four' => 'AbCd'])
        ->assertJsonPath('data.1.name', 'Ops mail')
        ->assertJsonPath('data.1.recipients', ['ops@example.com', 'cto@example.com'])
        ->assertJsonPath('data.1.webhook_hint', null)
        ->assertJsonPath('data.2.name', 'Slack')
        ->assertJsonPath('data.2.is_enabled', false)
        ->assertJsonPath('data.2.disabled_reason', 'webhook_rejected')
        ->assertJsonPath('data.2.webhook_hint', ['host' => 'hooks.slack.com', 'last_four' => 'WxYz']);

    expect($response->getContent())->not->toContain('secret-token')->not->toContain('webhook_url')->not->toContain('settings');
})->with([Role::Owner, Role::Admin, Role::Member, Role::Viewer]);

it('shows one channel of the organization', function (): void {
    $this->actingAs(Members::seat($this->organization, Role::Viewer))->getJson(channelsUrl($this->organization, $this->discord->id))
        ->assertOk()
        ->assertJsonPath('data.id', $this->discord->id)
        ->assertJsonPath('data.webhook_hint.host', 'discord.com');
});

it('hides the channels from a non member and the channels of other organizations', function (): void {
    $foreign = AlertChannel::query()->where('name', 'Elsewhere')->sole();

    $this->actingAs(Members::outsider())->getJson(channelsUrl($this->organization))->assertNotFound();
    $this->actingAs(Members::seat($this->organization, Role::Owner))->getJson(channelsUrl($this->organization, $foreign->id))->assertNotFound();
});
