<?php

declare(strict_types=1);

namespace App\Alerting\Settings;

final readonly class ChannelLimits
{
    public function __construct(
        public int $nameMaxLength,
        public int $webhookUrlMaxLength,
    ) {}
}
