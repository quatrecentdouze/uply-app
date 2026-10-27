<?php

declare(strict_types=1);

use App\Alerting\Enums\ChannelType;
use App\Alerting\Models\AlertChannel;
use App\Alerting\Settings\AlertingSettings;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use Tests\Support\Members;

const NEW_DISCORD_WEBHOOK = 'https://discord.com/api/webhooks/1234567890/new-discord-secret-QrSt';

const NEW_SLACK_WEBHOOK = 'https://hooks.slack.com/services/T000/B000/new-slack-secret-UvWx';

function createChannelUrl(Organization $organization): string
{
    return "/api/v1/organizations/{$organization->id}/alert-channels";
}

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
});

it('creates an email channel for the roles that manage channels', function (Role $role): void {
    $this->actingAs(Members::seat($this->organization, $role))
        ->postJson(createChannelUrl($this->organization), ['type' => 'email', 'name' => 'Ops mail', 'recipients' => ['Ops@Example.com', 'ops@example.com', 'cto@example.com']])
        ->assertCreated()
        ->assertJsonPath('data.type', 'email')
        ->assertJsonPath('data.name', 'Ops mail')
        ->assertJsonPath('data.is_enabled', true)
        ->assertJsonPath('data.recipients', ['ops@example.com', 'cto@example.com'])
        ->assertJsonPath('data.webhook_hint', null)
        ->assertJsonPath('data.reminder_interval_minutes', null);

    $channel = AlertChannel::query()->sole();
    expect($channel->organization_id)->toBe($this->organization->id)
        ->and($channel->recipients(1, 10)->addresses)->toBe(['ops@example.com', 'cto@example.com']);
})->with([Role::Owner, Role::Admin]);

it('creates a webhook channel and answers with a hint only', function (string $type, string $url, string $host, string $lastFour): void {
    $response = $this->actingAs(Members::seat($this->organization, Role::Admin))
        ->postJson(createChannelUrl($this->organization), ['type' => $type, 'name' => 'Chat', 'webhook_url' => $url, 'is_enabled' => false])
        ->assertCreated()
        ->assertJsonPath('data.type', $type)
        ->assertJsonPath('data.is_enabled', false)
        ->assertJsonPath('data.recipients', null)
        ->assertJsonPath('data.webhook_hint', ['host' => $host, 'last_four' => $lastFour]);

    $channel = AlertChannel::query()->sole();
    expect($channel->webhookUrl(app(AlertingSettings::class)->webhooks->policyFor(ChannelType::from($type)))->value)->toBe($url)
        ->and($response->getContent())->not->toContain('secret');
})->with([
    ['discord', NEW_DISCORD_WEBHOOK, 'discord.com', 'QrSt'],
    ['slack', NEW_SLACK_WEBHOOK, 'hooks.slack.com', 'UvWx'],
]);

it('refuses the roles that do not manage channels and hides the organization from a non member', function (): void {
    $payload = ['type' => 'email', 'name' => 'Ops', 'recipients' => ['ops@example.com']];

    $this->actingAs(Members::seat($this->organization, Role::Member))->postJson(createChannelUrl($this->organization), $payload)->assertForbidden();
    $this->actingAs(Members::seat($this->organization, Role::Viewer))->postJson(createChannelUrl($this->organization), $payload)->assertForbidden();
    $this->actingAs(Members::outsider())->postJson(createChannelUrl($this->organization), $payload)->assertNotFound();

    expect(AlertChannel::query()->count())->toBe(0);
});

it('validates each type of channel', function (array $payload, string $field): void {
    $this->actingAs(Members::seat($this->organization, Role::Owner))
        ->postJson(createChannelUrl($this->organization), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'unknown type' => [['type' => 'sms', 'name' => 'SMS'], 'type'],
    'no name' => [['type' => 'email', 'recipients' => ['a@example.com']], 'name'],
    'long name' => [['type' => 'email', 'name' => str_repeat('n', 81), 'recipients' => ['a@example.com']], 'name'],
    'email without recipients' => [['type' => 'email', 'name' => 'Mail'], 'recipients'],
    'email with no recipient' => [['type' => 'email', 'name' => 'Mail', 'recipients' => []], 'recipients'],
    'email with a bad address' => [['type' => 'email', 'name' => 'Mail', 'recipients' => ['not an address']], 'recipients'],
    'email with eleven recipients' => [['type' => 'email', 'name' => 'Mail', 'recipients' => array_map(fn (int $n): string => "r{$n}@example.com", range(1, 11))], 'recipients'],
    'email with a webhook' => [['type' => 'email', 'name' => 'Mail', 'recipients' => ['a@example.com'], 'webhook_url' => NEW_SLACK_WEBHOOK], 'webhook_url'],
    'discord without webhook' => [['type' => 'discord', 'name' => 'Discord'], 'webhook_url'],
    'discord with a slack webhook' => [['type' => 'discord', 'name' => 'Discord', 'webhook_url' => NEW_SLACK_WEBHOOK], 'webhook_url'],
    'discord over http' => [['type' => 'discord', 'name' => 'Discord', 'webhook_url' => 'http://discord.com/api/webhooks/1/token'], 'webhook_url'],
    'slack on another host' => [['type' => 'slack', 'name' => 'Slack', 'webhook_url' => 'https://hooks.example.com/services/T/B/x'], 'webhook_url'],
    'slack with recipients' => [['type' => 'slack', 'name' => 'Slack', 'webhook_url' => NEW_SLACK_WEBHOOK, 'recipients' => ['a@example.com']], 'recipients'],
    'long webhook' => [['type' => 'slack', 'name' => 'Slack', 'webhook_url' => NEW_SLACK_WEBHOOK.str_repeat('a', 2048)], 'webhook_url'],
]);

it('explains a refused webhook without repeating it', function (): void {
    $response = $this->actingAs(Members::seat($this->organization, Role::Owner))
        ->postJson(createChannelUrl($this->organization), ['type' => 'discord', 'name' => 'Discord', 'webhook_url' => 'https://evil.example.com/api/webhooks/1/leaked-secret'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.webhook_url.0', 'The URL must be a Discord webhook URL like https://discord.com/api/webhooks/...');

    expect($response->getContent())->not->toContain('leaked-secret');
});
