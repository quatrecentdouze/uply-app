<?php

declare(strict_types=1);

namespace App\Alerting\Values;

use App\Alerting\Enums\DecisionOutcome;

final readonly class DeliveryDecision
{
    public const int MAX_LAST_ERROR_LENGTH = 500;

    private function __construct(
        public DecisionOutcome $outcome,
        public ?int $retryInSeconds,
        public bool $disablesChannel,
        public ?string $lastError,
    ) {}

    public static function sent(): self
    {
        return new self(DecisionOutcome::Sent, null, false, null);
    }

    public static function retry(int $retryInSeconds, string $lastError): self
    {
        return new self(DecisionOutcome::Retry, $retryInSeconds, false, $lastError);
    }

    public static function failed(string $lastError, bool $disablesChannel = false): self
    {
        return new self(DecisionOutcome::Failed, null, $disablesChannel, $lastError);
    }
}
