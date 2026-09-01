<?php

declare(strict_types=1);

namespace App\Monitoring\Settings;

final readonly class DispatchSettings
{
    public function __construct(
        public DispatchTick $tick,
        public int $batchSize,
    ) {}
}
