<?php

declare(strict_types=1);

namespace App\Alerting\Http;

use App\Alerting\Enums\ChannelType;
use App\Alerting\Http\Requests\AlertChannelFields;
use App\Alerting\Settings\AlertingSettings;
use App\Shared\Meta\MetaSection;

final readonly class AlertingMetaSection implements MetaSection
{
    public function __construct(private AlertingSettings $settings) {}

    public function key(): string
    {
        return 'alerting';
    }

    public function describe(): array
    {
        return [
            'channel_types' => array_map(fn (ChannelType $type): string => $type->value, ChannelType::cases()),
            'email_recipients' => [
                'min' => $this->settings->email->minRecipients,
                'max' => $this->settings->email->maxRecipients,
            ],
            'test_sends_per_minute' => $this->settings->delivery->testSendsPerMinute,
            'max_channels_per_monitor' => $this->settings->channels->maxPerMonitor,
            'lengths' => [
                'channel_name' => ['min' => AlertChannelFields::NAME_MIN_LENGTH, 'max' => $this->settings->channels->nameMaxLength],
                'webhook_url' => ['max' => $this->settings->channels->webhookUrlMaxLength],
            ],
            'reminder_intervals_minutes' => $this->reminderIntervals(),
            'limits' => [
                'max_reminders_per_incident' => $this->settings->reminders->maxPerIncident,
            ],
        ];
    }

    private function reminderIntervals(): array
    {
        $minutes = array_values(array_filter($this->settings->reminders->intervalsMinutes, is_int(...)));

        return array_map(fn (int $interval): int => $interval, $minutes);
    }
}
