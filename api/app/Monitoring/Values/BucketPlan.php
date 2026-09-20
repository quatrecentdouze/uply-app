<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class BucketPlan
{
    public function __construct(
        public CarbonImmutable $firstStart,
        public int $bucketHours,
        public int $count,
    ) {
        if ($bucketHours < 1 || $count < 1) {
            throw new InvalidArgumentException('a bucket plan needs at least one bucket of at least one hour');
        }
    }

    public function end(): CarbonImmutable
    {
        return $this->firstStart->addHours($this->bucketHours * $this->count);
    }

    public function start(int $index): CarbonImmutable
    {
        return $this->firstStart->addHours($this->bucketHours * $index);
    }

    public function indexOf(CarbonImmutable $hourStart): ?int
    {
        if ($hourStart->lessThan($this->firstStart) || $hourStart->greaterThanOrEqualTo($this->end())) {
            return null;
        }

        return intdiv((int) $this->firstStart->diffInHours($hourStart), $this->bucketHours);
    }
}
