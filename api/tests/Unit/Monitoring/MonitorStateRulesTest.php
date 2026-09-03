<?php

declare(strict_types=1);

use App\Monitoring\Enums\FailureKind;
use App\Monitoring\Enums\MonitorState;
use App\Monitoring\Enums\StateTransition;
use App\Monitoring\Rules\MonitorStateRules;
use App\Monitoring\Values\CheckVerdict;
use App\Monitoring\Values\ConfirmationThresholds;
use App\Monitoring\Values\MonitorStateSnapshot;
use App\Monitoring\Values\StateAdvance;
use Carbon\CarbonImmutable;

function advanceState(MonitorStateSnapshot $snapshot, string $outcome, string $at, int $thresholds = 2): StateAdvance
{
    $verdict = $outcome === 'up' ? CheckVerdict::up() : CheckVerdict::down(FailureKind::HttpStatus);

    return MonitorStateRules::advance($snapshot, $verdict, new ConfirmationThresholds($thresholds, $thresholds), CarbonImmutable::parse($at, 'UTC'));
}

function stateAfter(string $at, string ...$outcomes): StateAdvance
{
    $snapshot = MonitorStateSnapshot::initial();
    $advance = null;
    foreach ($outcomes as $outcome) {
        $advance = advanceState($snapshot, $outcome, $at);
        $snapshot = $advance->snapshot;
    }

    return $advance ?? throw new LogicException('no outcome given');
}

it('starts pending without any streak', function (): void {
    $snapshot = MonitorStateSnapshot::initial();

    expect($snapshot->state)->toBe(MonitorState::Pending)
        ->and($snapshot->consecutiveFailures)->toBe(0)
        ->and($snapshot->consecutiveSuccesses)->toBe(0);
});

it('stays pending after one result and asks for a confirmation', function (string $outcome): void {
    $advance = advanceState(MonitorStateSnapshot::initial(), $outcome, '2026-10-28 12:00:00');

    expect($advance->snapshot->state)->toBe(MonitorState::Pending)
        ->and($advance->transition)->toBe(StateTransition::None)
        ->and($advance->awaitsConfirmation)->toBeTrue();
})->with(['up', 'down']);

it('confirms up after two successes without opening anything', function (): void {
    $first = advanceState(MonitorStateSnapshot::initial(), 'up', '2026-10-28 12:00:00');
    $second = advanceState($first->snapshot, 'up', '2026-10-28 12:00:20');

    expect($second->snapshot->state)->toBe(MonitorState::Up)
        ->and($second->transition)->toBe(StateTransition::None)
        ->and($second->stateChanged())->toBeTrue()
        ->and($second->awaitsConfirmation)->toBeFalse()
        ->and($second->snapshot->stateChangedAt?->toTimeString())->toBe('12:00:20');
});

it('goes down on the second failure and remembers when the streak began', function (): void {
    $up = stateAfter('2026-10-28 11:00:00', 'up', 'up')->snapshot;

    $first = advanceState($up, 'down', '2026-10-28 12:00:00');
    expect($first->snapshot->state)->toBe(MonitorState::Up)
        ->and($first->transition)->toBe(StateTransition::None)
        ->and($first->awaitsConfirmation)->toBeTrue()
        ->and($first->snapshot->consecutiveFailures)->toBe(1);

    $second = advanceState($first->snapshot, 'down', '2026-10-28 12:00:20');
    expect($second->snapshot->state)->toBe(MonitorState::Down)
        ->and($second->transition)->toBe(StateTransition::WentDown)
        ->and($second->awaitsConfirmation)->toBeFalse()
        ->and($second->snapshot->streakStartedAt?->toTimeString())->toBe('12:00:00')
        ->and($second->snapshot->stateChangedAt?->toTimeString())->toBe('12:00:20');
});

it('recovers on the second success and dates the recovery from the first one', function (): void {
    $down = stateAfter('2026-10-28 11:00:00', 'up', 'up', 'down', 'down')->snapshot;

    $first = advanceState($down, 'up', '2026-10-28 12:05:00');
    expect($first->snapshot->state)->toBe(MonitorState::Down)
        ->and($first->awaitsConfirmation)->toBeTrue();

    $second = advanceState($first->snapshot, 'up', '2026-10-28 12:05:20');
    expect($second->snapshot->state)->toBe(MonitorState::Up)
        ->and($second->transition)->toBe(StateTransition::Recovered)
        ->and($second->snapshot->streakStartedAt?->toTimeString())->toBe('12:05:00');
});

it('goes down from pending when the target fails from the start', function (): void {
    expect(stateAfter('2026-10-28 12:00:00', 'down', 'down')->transition)->toBe(StateTransition::WentDown);
});

it('stays quiet while the confirmed state keeps being observed', function (): void {
    $advance = stateAfter('2026-10-28 12:00:00', 'down', 'down', 'down', 'down');

    expect($advance->transition)->toBe(StateTransition::None)
        ->and($advance->awaitsConfirmation)->toBeFalse()
        ->and($advance->snapshot->consecutiveFailures)->toBe(4);
});

it('resets the streak when a single result disagrees', function (): void {
    $advance = stateAfter('2026-10-28 12:00:00', 'up', 'up', 'down', 'up');

    expect($advance->snapshot->state)->toBe(MonitorState::Up)
        ->and($advance->snapshot->consecutiveFailures)->toBe(0)
        ->and($advance->snapshot->consecutiveSuccesses)->toBe(1)
        ->and($advance->transition)->toBe(StateTransition::None);
});

it('follows other thresholds from the settings', function (): void {
    $snapshot = MonitorStateSnapshot::initial();
    foreach (['12:00:00', '12:00:20'] as $at) {
        $snapshot = advanceState($snapshot, 'down', "2026-10-28 {$at}", 3)->snapshot;
    }

    expect($snapshot->state)->toBe(MonitorState::Pending)
        ->and(advanceState($snapshot, 'down', '2026-10-28 12:00:40', 3)->transition)->toBe(StateTransition::WentDown);
});
