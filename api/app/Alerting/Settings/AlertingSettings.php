<?php

declare(strict_types=1);

namespace App\Alerting\Settings;

final readonly class AlertingSettings
{
    public function __construct(
        public DeliverySettings $delivery,
        public WebhookSettings $webhooks,
        public EmailSettings $email,
        public ChannelLimits $channels,
        public PresentationSettings $presentation,
        public ReminderSettings $reminders,
        public ?string $demoChannelsFile,
    ) {}
}
