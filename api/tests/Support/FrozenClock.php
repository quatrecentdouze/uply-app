<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Clock;
use Carbon\CarbonImmutable;

final class FrozenClock implements Clock
{
    public function __construct(private CarbonImmutable $now) {}

    public static function at(string $moment): self
    {
        return new self(CarbonImmutable::parse($moment, 'UTC'));
    }

    public function now(): CarbonImmutable
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now = $this->now->addSeconds($seconds);
    }

    public function moveTo(CarbonImmutable $moment): void
    {
        $this->now = $moment;
    }
}
