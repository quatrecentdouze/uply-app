<?php

declare(strict_types=1);

namespace App\Alerting\Values;

final readonly class ChannelDraft
{
    public function __construct(
        public ?string $name,
        public ?bool $isEnabled,
        public ?EmailRecipients $recipients,
        public ?WebhookUrl $webhook,
    ) {}
}
