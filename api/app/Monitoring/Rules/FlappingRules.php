<?php

declare(strict_types=1);

namespace App\Monitoring\Rules;

use App\Monitoring\Enums\FlappingTurn;
use App\Monitoring\Values\FlappingState;
use App\Monitoring\Values\FlappingStep;
use Carbon\CarbonImmutable;

final class FlappingRules
{
    public static function afterTransition(FlappingState $current, CarbonImmutable $changedAt, int $changes, int $windowMinutes): FlappingStep
    {
        $kept = new FlappingState($current->flappingSince, array_slice([...$current->stateChanges, $changedAt], -$changes));
        if ($current->isFlapping()) {
            return FlappingStep::unchanged($kept);
        }
        $first = $kept->firstChangeAt() ?? $changedAt;
        if ($kept->changeCount() < $changes || $first->lessThan($changedAt->subMinutes($windowMinutes))) {
            return FlappingStep::unchanged($kept);
        }

        return new FlappingStep(new FlappingState($changedAt, $kept->stateChanges), FlappingTurn::Started, $changedAt);
    }

    public static function afterCheck(FlappingState $current, CarbonImmutable $checkedAt, int $stableMinutes): FlappingStep
    {
        $lastChangeAt = $current->lastChangeAt();
        if (! $current->isFlapping() || ($lastChangeAt !== null && $checkedAt->lessThan($lastChangeAt->addMinutes($stableMinutes)))) {
            return FlappingStep::unchanged($current);
        }

        return new FlappingStep(new FlappingState(null, $current->stateChanges), FlappingTurn::Ended, $current->flappingSince);
    }
}
