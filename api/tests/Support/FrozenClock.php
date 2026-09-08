<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Clock;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

final class FrozenClock implements Clock
{
    public function __construct(
        private CarbonImmutable $now,
        private readonly bool $freezesLaravelTime = false,
    ) {
        $this->syncLaravelTime();
    }

    public static function at(string $moment, bool $freezesLaravelTime = false): self
    {
        return new self(CarbonImmutable::parse($moment, 'UTC'), $freezesLaravelTime);
    }

    public function now(): CarbonImmutable
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->moveTo($this->now->addSeconds($seconds));
    }

    public function moveTo(CarbonImmutable $moment): void
    {
        $this->now = $moment;
        $this->syncLaravelTime();
    }

    private function syncLaravelTime(): void
    {
        if ($this->freezesLaravelTime) {
            Carbon::setTestNow($this->now);
            CarbonImmutable::setTestNow($this->now);
        }
    }
}
