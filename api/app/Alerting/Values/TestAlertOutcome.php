<?php

declare(strict_types=1);

namespace App\Alerting\Values;

use App\Alerting\Enums\TestAlertStatus;

final readonly class TestAlertOutcome
{
    private function __construct(
        public TestAlertStatus $status,
        public ?string $deliveryId,
        public ?int $retryInSeconds,
    ) {}

    public static function queued(string $deliveryId): self
    {
        return new self(TestAlertStatus::Queued, $deliveryId, null);
    }

    public static function limited(int $retryInSeconds): self
    {
        return new self(TestAlertStatus::Limited, null, $retryInSeconds);
    }

    public static function channelNotFound(): self
    {
        return new self(TestAlertStatus::ChannelNotFound, null, null);
    }
}
