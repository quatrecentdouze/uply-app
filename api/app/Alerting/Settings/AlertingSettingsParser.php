<?php

declare(strict_types=1);

namespace App\Alerting\Settings;

use App\Alerting\Values\WebhookPolicy;
use App\Shared\Settings\SettingsReader;
use InvalidArgumentException;

final class AlertingSettingsParser
{
    private const string COLOR_PATTERN = '/^#[0-9a-f]{6}$/i';

    private const array FRONTEND_SCHEMES = ['http', 'https'];

    public function parse(string $source, array $values): AlertingSettings
    {
        $reader = new SettingsReader($source, $values);

        return new AlertingSettings(
            delivery: $this->delivery($reader),
            webhooks: $this->webhooks($reader),
            email: $this->email($reader),
            channels: new ChannelLimits(
                $reader->integer('channels.name_max_length', 1),
                $reader->integer('channels.webhook_url_max_length', 1),
                $reader->integer('channels.max_per_monitor', 1),
            ),
            presentation: $this->presentation($reader),
            reminders: $this->reminders($reader),
            demoChannelsFile: $reader->optionalText('demo_channels_file'),
        );
    }

    private function delivery(SettingsReader $reader): DeliverySettings
    {
        $maxAttempts = $reader->integer('delivery.max_attempts', 1);
        $backoff = $reader->integerList('delivery.backoff_seconds', 1);
        if (count($backoff) !== $maxAttempts - 1) {
            throw $reader->invalid('delivery.backoff_seconds', 'must hold one delay per retry, delivery.max_attempts minus one');
        }

        return new DeliverySettings(
            maxAttempts: $maxAttempts,
            backoffSeconds: $backoff,
            messagesPerSecondPerWebhook: $reader->integer('delivery.messages_per_second_per_webhook', 1),
            testSendsPerMinute: $reader->integer('delivery.test_sends_per_minute', 1),
        );
    }

    private function reminders(SettingsReader $reader): ReminderSettings
    {
        $intervals = array_values(array_filter($reader->integerList('reminders.intervals_minutes', 1), is_int(...)));
        if (count(array_unique($intervals)) !== count($intervals)) {
            throw $reader->invalid('reminders.intervals_minutes', 'must list distinct minutes');
        }

        return new ReminderSettings($intervals, $reader->integer('reminders.max_per_incident', 1));
    }

    private function webhooks(SettingsReader $reader): WebhookSettings
    {
        return new WebhookSettings(
            timeoutSeconds: $reader->integer('webhooks.timeout_seconds', 1),
            connectTimeoutSeconds: $reader->integer('webhooks.connect_timeout_seconds', 1),
            discord: $this->policy($reader, 'webhooks.discord'),
            slack: $this->policy($reader, 'webhooks.slack'),
        );
    }

    private function policy(SettingsReader $reader, string $key): WebhookPolicy
    {
        $hosts = array_map(static fn (mixed $host): string => is_string($host) ? strtolower($host) : '', $reader->textList("{$key}.hosts"));
        $pattern = $reader->text("{$key}.path_pattern");
        try {
            return new WebhookPolicy($hosts, $pattern);
        } catch (InvalidArgumentException $exception) {
            throw $reader->invalid("{$key}.path_pattern", $exception->getMessage());
        }
    }

    private function email(SettingsReader $reader): EmailSettings
    {
        $minimum = $reader->integer('email.min_recipients', 1);

        return new EmailSettings($minimum, $reader->integer('email.max_recipients', $minimum));
    }

    private function presentation(SettingsReader $reader): PresentationSettings
    {
        $frontendUrl = $reader->text('presentation.frontend_url');
        $scheme = parse_url($frontendUrl, PHP_URL_SCHEME);
        if (filter_var($frontendUrl, FILTER_VALIDATE_URL) === false || ! in_array($scheme, self::FRONTEND_SCHEMES, true)) {
            throw $reader->invalid('presentation.frontend_url', 'must be an http or https url');
        }
        $titleMax = $reader->integer('presentation.discord_title_max_length', 1);

        return new PresentationSettings(
            frontendUrl: $frontendUrl,
            downColor: $this->color($reader, 'presentation.colors.down'),
            upColor: $this->color($reader, 'presentation.colors.up'),
            testColor: $this->color($reader, 'presentation.colors.test'),
            certificateExpiringColor: $this->color($reader, 'presentation.colors.certificate_expiring'),
            discordUsername: $reader->text('presentation.discord_username'),
            discordTitleMaxLength: $titleMax,
            discordDescriptionMaxLength: $reader->integer('presentation.discord_description_max_length', 1),
            discordFieldValueMaxLength: $reader->integer('presentation.discord_field_value_max_length', 1),
        );
    }

    private function color(SettingsReader $reader, string $key): string
    {
        $color = $reader->text($key);
        if (preg_match(self::COLOR_PATTERN, $color) !== 1) {
            throw $reader->invalid($key, 'must be a color like #f23b39');
        }

        return strtolower($color);
    }
}
