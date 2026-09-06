<?php

declare(strict_types=1);

use App\Alerting\Enums\DeliveryKind;
use App\Alerting\Settings\AlertingSettingsParser;
use App\Shared\Settings\InvalidSettingException;

function shippedAlertingConfig(): array
{
    return require dirname(__DIR__, 3).'/config/alerting.php';
}

it('parses the shipped alerting file', function (): void {
    $settings = (new AlertingSettingsParser)->parse('config/alerting.php', shippedAlertingConfig());

    expect($settings->delivery->maxAttempts)->toBe(5)
        ->and($settings->delivery->backoffSeconds)->toBe([10, 30, 60, 120])
        ->and($settings->delivery->messagesPerSecondPerWebhook)->toBe(1)
        ->and($settings->delivery->testSendsPerMinute)->toBe(5)
        ->and($settings->webhooks->timeoutSeconds)->toBe(10)
        ->and($settings->webhooks->connectTimeoutSeconds)->toBe(5)
        ->and($settings->webhooks->discord->hosts)->toBe(['discord.com', 'discordapp.com', 'ptb.discord.com', 'canary.discord.com'])
        ->and($settings->webhooks->slack->hosts)->toBe(['hooks.slack.com'])
        ->and($settings->email->minRecipients)->toBe(1)
        ->and($settings->email->maxRecipients)->toBe(10)
        ->and($settings->presentation->frontendUrl)->toBe('http://localhost:5173')
        ->and($settings->presentation->colorFor(DeliveryKind::Down))->toBe('#f23b39')
        ->and($settings->presentation->colorFor(DeliveryKind::Up))->toBe('#34b591')
        ->and($settings->presentation->colorFor(DeliveryKind::Test))->toBe('#f36e29')
        ->and($settings->presentation->discordUsername)->toBe('Uply')
        ->and($settings->presentation->discordTitleMaxLength)->toBe(256)
        ->and($settings->presentation->discordFieldValueMaxLength)->toBe(1024)
        ->and($settings->presentation->incidentLink('01abc'))->toBe('http://localhost:5173/incidents/01abc')
        ->and($settings->demoChannelsFile)->toBeNull();
});

it('rejects an invalid alerting setting with its file and key', function (string $key, mixed $value, ?string $reportedKey = null): void {
    $config = shippedAlertingConfig();
    data_set($config, $key, $value);

    try {
        (new AlertingSettingsParser)->parse('config/alerting.php', $config);
        $this->fail("{$key} was accepted");
    } catch (InvalidSettingException $exception) {
        expect($exception->source)->toBe('config/alerting.php')
            ->and($exception->key)->toBe($reportedKey ?? $key);
    }
})->with([
    'no attempt' => ['delivery.max_attempts', 0],
    'backoff of the wrong length' => ['delivery.backoff_seconds', [10, 30]],
    'backoff with zero' => ['delivery.backoff_seconds', [0, 30, 60, 120]],
    'webhook rate zero' => ['delivery.messages_per_second_per_webhook', 0],
    'test sends zero' => ['delivery.test_sends_per_minute', 0],
    'webhook timeout zero' => ['webhooks.timeout_seconds', 0],
    'webhook connect timeout zero' => ['webhooks.connect_timeout_seconds', 0],
    'discord without host' => ['webhooks.discord.hosts', []],
    'discord pattern broken' => ['webhooks.discord.path_pattern', '~^/api/webhooks/(~'],
    'slack host not a text' => ['webhooks.slack.hosts', [42]],
    'slack pattern blank' => ['webhooks.slack.path_pattern', ''],
    'no recipient' => ['email.min_recipients', 0],
    'max below min' => ['email.max_recipients', 0],
    'frontend not a url' => ['presentation.frontend_url', 'localhost'],
    'frontend not http' => ['presentation.frontend_url', 'ftp://localhost'],
    'down color' => ['presentation.colors.down', 'red'],
    'up color' => ['presentation.colors.up', '#34b59'],
    'test color' => ['presentation.colors.test', 42],
    'discord username blank' => ['presentation.discord_username', ' '],
    'discord title zero' => ['presentation.discord_title_max_length', 0],
    'discord description zero' => ['presentation.discord_description_max_length', 0],
    'discord field zero' => ['presentation.discord_field_value_max_length', 0],
    'demo file blank' => ['demo_channels_file', ''],
]);
