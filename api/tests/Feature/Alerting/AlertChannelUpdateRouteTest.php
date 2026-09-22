<?php

declare(strict_types=1);

use App\Alerting\Enums\ChannelType;
use App\Alerting\Enums\DisabledReason;
use App\Alerting\Models\AlertChannel;
use App\Alerting\Settings\AlertingSettings;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use Tests\Support\Members;

const OLD_SLACK_WEBHOOK = 'https://hooks.slack.com/services/T000/B000/old-slack-secret-AaAa';

const NEXT_SLACK_WEBHOOK = 'https://hooks.slack.com/services/T000/B000/next-slack-secret-BbBb';

function updateChannelUrl(AlertChannel $channel): string
{
    return "/api/v1/organizations/{$channel->organization_id}/alert-channels/{$channel->id}";
}

function storedSlackWebhook(AlertChannel $channel): string
{
    return ($channel->fresh() ?? $channel)->webhookUrl(app(AlertingSettings::class)->webhooks->policyFor(ChannelType::Slack))->value;
}

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $inOrganization = ['organization_id' => $this->organization->id];
    $this->slack = AlertChannel::factory()->state($inOrganization)->slack(OLD_SLACK_WEBHOOK)->create([
        'name' => 'Slack',
        'is_enabled' => false,
        'disabled_reason' => DisabledReason::WebhookRejected,
    ]);
    $this->email = AlertChannel::factory()->state($inOrganization)->create(['name' => 'Mail', 'settings' => [AlertChannel::RECIPIENTS_SETTING => ['ops@example.com']]]);
});

it('renames a channel and keeps its webhook when no new url comes, for the roles that manage channels', function (Role $role): void {
    $this->actingAs(Members::seat($this->organization, $role))
        ->patchJson(updateChannelUrl($this->slack), ['name' => 'Team Slack'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Team Slack')
        ->assertJsonPath('data.webhook_hint.last_four', 'AaAa')
        ->assertJsonPath('data.is_enabled', false)
        ->assertJsonPath('data.disabled_reason', 'webhook_rejected');

    expect(storedSlackWebhook($this->slack))->toBe(OLD_SLACK_WEBHOOK);
})->with([Role::Owner, Role::Admin]);

it('replaces the webhook and enables the channel again on request only', function (): void {
    $this->actingAs(Members::seat($this->organization, Role::Admin));

    $this->patchJson(updateChannelUrl($this->slack), ['webhook_url' => NEXT_SLACK_WEBHOOK])
        ->assertOk()
        ->assertJsonPath('data.webhook_hint.last_four', 'BbBb')
        ->assertJsonPath('data.is_enabled', false);
    $this->patchJson(updateChannelUrl($this->slack), ['is_enabled' => true])
        ->assertOk()
        ->assertJsonPath('data.is_enabled', true)
        ->assertJsonPath('data.disabled_reason', null);

    expect(storedSlackWebhook($this->slack))->toBe(NEXT_SLACK_WEBHOOK);
});

it('replaces the recipients of an email channel', function (): void {
    $this->actingAs(Members::seat($this->organization, Role::Owner))
        ->patchJson(updateChannelUrl($this->email), ['recipients' => ['oncall@example.com', 'ONCALL@example.com']])
        ->assertOk()
        ->assertJsonPath('data.recipients', ['oncall@example.com']);
});

it('refuses a field of another type and a change of type', function (string $channel, array $payload, string $field): void {
    $this->actingAs(Members::seat($this->organization, Role::Owner))
        ->patchJson(updateChannelUrl($this->{$channel}), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'webhook on email' => ['email', ['webhook_url' => NEXT_SLACK_WEBHOOK], 'webhook_url'],
    'recipients on slack' => ['slack', ['recipients' => ['a@example.com']], 'recipients'],
    'type change' => ['email', ['type' => 'slack'], 'type'],
    'discord url on slack' => ['slack', ['webhook_url' => 'https://discord.com/api/webhooks/1/token'], 'webhook_url'],
    'empty name' => ['slack', ['name' => ''], 'name'],
]);

it('refuses the roles that do not manage channels and the channels of another organization', function (): void {
    $foreign = AlertChannel::factory()->create();

    $this->actingAs(Members::seat($this->organization, Role::Member))->patchJson(updateChannelUrl($this->slack), ['name' => 'X'])->assertForbidden();
    $this->actingAs(Members::outsider())->patchJson(updateChannelUrl($this->slack), ['name' => 'X'])->assertNotFound();
    $this->actingAs(Members::seat($this->organization, Role::Owner))
        ->patchJson("/api/v1/organizations/{$this->organization->id}/alert-channels/{$foreign->id}", ['name' => 'X'])
        ->assertNotFound();

    expect($this->slack->fresh()?->name)->toBe('Slack');
});
