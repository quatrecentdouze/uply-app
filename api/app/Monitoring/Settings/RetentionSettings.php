<?php

declare(strict_types=1);

namespace App\Monitoring\Settings;

final readonly class RetentionSettings
{
    public function __construct(
        public int $rawResultsDays,
        public int $partitionsAheadDays,
        public int $hourlyStatsDays,
    ) {}
}
