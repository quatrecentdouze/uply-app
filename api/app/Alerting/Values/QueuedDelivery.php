<?php

declare(strict_types=1);

namespace App\Alerting\Values;

final readonly class QueuedDelivery
{
    public function __construct(
        public string $deliveryId,
        public string $channelId,
        public bool $usesWebhook,
    ) {}
}
