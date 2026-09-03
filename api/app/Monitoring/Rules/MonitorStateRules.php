<?php

declare(strict_types=1);

namespace App\Monitoring\Rules;

use App\Monitoring\Enums\CheckOutcome;
use App\Monitoring\Enums\MonitorState;
use App\Monitoring\Enums\StateTransition;
use App\Monitoring\Values\CheckVerdict;
use App\Monitoring\Values\ConfirmationThresholds;
use App\Monitoring\Values\MonitorStateSnapshot;
use App\Monitoring\Values\StateAdvance;
use Carbon\CarbonImmutable;

final class MonitorStateRules
{
    public static function advance(
        MonitorStateSnapshot $current,
        CheckVerdict $verdict,
        ConfirmationThresholds $thresholds,
        CarbonImmutable $checkedAt,
    ): StateAdvance {
        $isUp = $verdict->outcome === CheckOutcome::Up;
        $failures = $isUp ? 0 : $current->consecutiveFailures + 1;
        $successes = $isUp ? $current->consecutiveSuccesses + 1 : 0;
        $streakLength = $isUp ? $successes : $failures;
        $required = $isUp ? $thresholds->successesToConfirmUp : $thresholds->failuresToConfirmDown;
        $observedState = $verdict->outcome->confirmedState();

        $state = $streakLength >= $required ? $observedState : $current->state;
        $streakStartedAt = $streakLength === 1 ? $checkedAt : ($current->streakStartedAt ?? $checkedAt);
        $stateChangedAt = $state !== $current->state ? $checkedAt : $current->stateChangedAt;

        return new StateAdvance(
            snapshot: new MonitorStateSnapshot($state, $failures, $successes, $streakStartedAt, $stateChangedAt),
            previousState: $current->state,
            transition: self::transition($current->state, $state),
            awaitsConfirmation: $state !== $observedState,
        );
    }

    private static function transition(MonitorState $from, MonitorState $to): StateTransition
    {
        return match (true) {
            $to === MonitorState::Down && $from !== MonitorState::Down => StateTransition::WentDown,
            $to === MonitorState::Up && $from === MonitorState::Down => StateTransition::Recovered,
            default => StateTransition::None,
        };
    }
}
