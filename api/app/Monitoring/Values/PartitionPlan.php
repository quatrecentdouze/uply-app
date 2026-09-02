<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

final readonly class PartitionPlan
{
    public function __construct(
        public array $daysToCreate,
        public array $partitionsToDrop,
    ) {}
}
