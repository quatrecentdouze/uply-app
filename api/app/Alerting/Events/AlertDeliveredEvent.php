<?php

declare(strict_types=1);

namespace App\Alerting\Events;

use App\Alerting\Enums\ChannelType;
use App\Alerting\Enums\DeliveryKind;
use App\Alerting\Enums\DeliveryStatus;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class AlertDeliveredEvent implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $deliveryId,
        public string $organizationId,
        public string $channelId,
        public ChannelType $channelType,
        public ?string $incidentId,
        public DeliveryKind $kind,
        public DeliveryStatus $status,
        public int $attempts,
        public CarbonImmutable $finishedAt,
    ) {}

    public function wasSent(): bool
    {
        return $this->status === DeliveryStatus::Sent;
    }
}
