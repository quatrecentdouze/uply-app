<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use App\Monitoring\Enums\MonitorState;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class MonitorStateSnapshot
{
    public function __construct(
        public MonitorState $state,
        public int $consecutiveFailures,
        public int $consecutiveSuccesses,
        public ?CarbonImmutable $streakStartedAt,
        public ?CarbonImmutable $stateChangedAt,
    ) {
        if ($consecutiveFailures < 0 || $consecutiveSuccesses < 0) {
            throw new InvalidArgumentException('streak counters cannot be negative');
        }
        if ($consecutiveFailures > 0 && $consecutiveSuccesses > 0) {
            throw new InvalidArgumentException('a monitor is either on a failure streak or on a success streak');
        }
    }

    public static function initial(): self
    {
        return new self(MonitorState::Pending, 0, 0, null, null);
    }
}
