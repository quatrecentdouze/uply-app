<?php

declare(strict_types=1);

namespace App\Alerting\Settings;

use App\Alerting\Values\RetryPolicy;

final readonly class DeliverySettings
{
    public function __construct(
        public int $maxAttempts,
        public array $backoffSeconds,
        public int $messagesPerSecondPerWebhook,
        public int $testSendsPerMinute,
    ) {}

    public function retryPolicy(): RetryPolicy
    {
        return new RetryPolicy($this->maxAttempts, $this->backoffSeconds);
    }
}
