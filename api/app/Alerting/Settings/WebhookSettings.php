<?php

declare(strict_types=1);

namespace App\Alerting\Settings;

use App\Alerting\Enums\ChannelType;
use App\Alerting\Values\WebhookPolicy;
use InvalidArgumentException;

final readonly class WebhookSettings
{
    public function __construct(
        public int $timeoutSeconds,
        public int $connectTimeoutSeconds,
        public WebhookPolicy $discord,
        public WebhookPolicy $slack,
    ) {}

    public function policyFor(ChannelType $type): WebhookPolicy
    {
        return match ($type) {
            ChannelType::Discord => $this->discord,
            ChannelType::Slack => $this->slack,
            ChannelType::Email => throw new InvalidArgumentException('an email channel has no webhook'),
        };
    }
}
