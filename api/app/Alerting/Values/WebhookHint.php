<?php

declare(strict_types=1);

namespace App\Alerting\Values;

final readonly class WebhookHint
{
    public function __construct(
        public string $host,
        public string $lastFour,
    ) {}
}
