<?php

declare(strict_types=1);

use App\Alerting\Values\EmailRecipients;
use App\Alerting\Values\WebhookPolicy;
use App\Alerting\Values\WebhookUrl;

function discordPolicy(): WebhookPolicy
{
    return new WebhookPolicy(['discord.com', 'discordapp.com', 'ptb.discord.com', 'canary.discord.com'], '~^/api/webhooks/[0-9]+/[A-Za-z0-9_-]+$~');
}

function slackPolicy(): WebhookPolicy
{
    return new WebhookPolicy(['hooks.slack.com'], '~^/services/[A-Za-z0-9_-]+(/[A-Za-z0-9_-]+)*$~');
}

it('accepts the webhook urls of each provider', function (string $url, WebhookPolicy $policy): void {
    expect(WebhookUrl::parse($url, $policy)->value)->toBe($url);
})->with([
    'discord' => ['https://discord.com/api/webhooks/1234567890/AbC-_9', discordPolicy()],
    'discord canary' => ['https://canary.discord.com/api/webhooks/1/x', discordPolicy()],
    'discord with thread query' => ['https://discordapp.com/api/webhooks/1/x?thread_id=2', discordPolicy()],
    'slack' => ['https://hooks.slack.com/services/T000/B000/XXXX', slackPolicy()],
]);

it('refuses a webhook url outside its provider', function (string $url, WebhookPolicy $policy): void {
    expect(fn () => WebhookUrl::parse($url, $policy))->toThrow(InvalidArgumentException::class);
})->with([
    'plain http' => ['http://discord.com/api/webhooks/1/x', discordPolicy()],
    'other host' => ['https://discord.example.com/api/webhooks/1/x', discordPolicy()],
    'host suffix trick' => ['https://discord.com.evil.test/api/webhooks/1/x', discordPolicy()],
    'wrong path' => ['https://discord.com/api/channels/1/x', discordPolicy()],
    'path traversal' => ['https://discord.com/api/webhooks/1/../../x', discordPolicy()],
    'credentials' => ['https://user:pass@discord.com/api/webhooks/1/x', discordPolicy()],
    'explicit port' => ['https://discord.com:8443/api/webhooks/1/x', discordPolicy()],
    'slack url on discord' => ['https://hooks.slack.com/services/T/B/X', discordPolicy()],
    'slack other path' => ['https://hooks.slack.com/workflows/T/B', slackPolicy()],
    'empty' => ['', slackPolicy()],
]);

it('lowercases and dedupes email recipients', function (): void {
    expect(EmailRecipients::parse([' Ops@Example.com', 'ops@example.com', 'oncall@example.com'], 1, 10)->addresses)
        ->toBe(['ops@example.com', 'oncall@example.com']);
});

it('refuses invalid or too many email recipients', function (array $addresses): void {
    expect(fn () => EmailRecipients::parse($addresses, 1, 2))->toThrow(InvalidArgumentException::class);
})->with([
    'none' => [[]],
    'not an address' => [['ops@']],
    'not a text' => [[42]],
    'too many' => [['a@example.com', 'b@example.com', 'c@example.com']],
]);
