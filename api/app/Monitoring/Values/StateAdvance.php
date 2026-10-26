<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use App\Monitoring\Enums\MonitorState;
use App\Monitoring\Enums\StateTransition;

final readonly class StateAdvance
{
    public function __construct(
        public MonitorStateSnapshot $snapshot,
        public MonitorState $previousState,
        public StateTransition $transition,
        public bool $awaitsConfirmation,
    ) {}

    public static function unchanged(MonitorStateSnapshot $snapshot): self
    {
        return new self($snapshot, $snapshot->state, StateTransition::None, false);
    }

    public function stateChanged(): bool
    {
        return $this->snapshot->state !== $this->previousState;
    }
}
