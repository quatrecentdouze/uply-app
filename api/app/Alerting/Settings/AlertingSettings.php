<?php

declare(strict_types=1);

namespace App\Alerting\Settings;

final readonly class AlertingSettings
{
    public function __construct(
        public DeliverySettings $delivery,
        public WebhookSettings $webhooks,
        public EmailSettings $email,
        public PresentationSettings $presentation,
        public ?string $demoChannelsFile,
    ) {}
}
