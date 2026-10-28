<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use App\Monitoring\Enums\FlappingTurn;
use Carbon\CarbonImmutable;

final readonly class FlappingStep
{
    public function __construct(
        public FlappingState $state,
        public FlappingTurn $turn,
        public ?CarbonImmutable $episodeStartedAt,
    ) {}

    public static function unchanged(FlappingState $state): self
    {
        return new self($state, FlappingTurn::None, $state->flappingSince);
    }
}
