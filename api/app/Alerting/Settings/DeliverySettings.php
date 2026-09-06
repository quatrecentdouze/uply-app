<?php

declare(strict_types=1);

namespace App\Alerting\Settings;

final readonly class DeliverySettings
{
    public function __construct(
        public int $maxAttempts,
        public array $backoffSeconds,
        public int $messagesPerSecondPerWebhook,
        public int $testSendsPerMinute,
    ) {}
}
