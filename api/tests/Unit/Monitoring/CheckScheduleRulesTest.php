<?php

declare(strict_types=1);

use App\Monitoring\Enums\MonitorState;
use App\Monitoring\Enums\StateTransition;
use App\Monitoring\Rules\CheckOutcomeRules;
use App\Monitoring\Rules\CheckScheduleRules;
use App\Monitoring\Rules\MonitorStateRules;
use App\Monitoring\Values\AcceptedStatusCodes;
use App\Monitoring\Values\ConfirmationThresholds;
use App\Monitoring\Values\MonitorStateSnapshot;
use App\Monitoring\Values\ProbeResult;
use App\Monitoring\Values\ProbeTimings;
use Carbon\CarbonImmutable;

function utc(string $moment): CarbonImmutable
{
    return CarbonImmutable::parse($moment, 'UTC');
}

it('moves the next check forward from the scheduled time, not from the check time', function (): void {
    $next = CheckScheduleRules::nextCheckAt(utc('2026-10-28 12:00:00'), utc('2026-10-28 12:00:00.900'), 60, 20, false);

    expect($next->toIso8601String())->toBe('2026-10-28T12:01:00+00:00');
});

it('does not drift when every check starts a little late', function (): void {
    $scheduled = utc('2026-10-28 12:00:00');
    foreach (range(1, 100) as $check) {
        $scheduled = CheckScheduleRules::nextCheckAt($scheduled, $scheduled->addMilliseconds(950), 30, 20, false);
    }

    expect($scheduled->toIso8601String())->toBe('2026-10-28T12:50:00+00:00');
});

it('skips the slots missed while the workers were away', function (): void {
    $next = CheckScheduleRules::nextCheckAt(utc('2026-10-28 12:00:00'), utc('2026-10-28 12:05:30'), 60, 20, false);

    expect($next->toIso8601String())->toBe('2026-10-28T12:06:00+00:00');
});

it('rechecks after the delay while the result waits for its confirmation', function (): void {
    $next = CheckScheduleRules::nextCheckAt(utc('2026-10-28 12:00:00'), utc('2026-10-28 12:00:01'), 60, 20, true);

    expect($next->toIso8601String())->toBe('2026-10-28T12:00:21+00:00');
});

it('keeps the regular slot when it comes before the recheck', function (): void {
    $next = CheckScheduleRules::nextCheckAt(utc('2026-10-28 12:00:00'), utc('2026-10-28 12:00:00'), 30, 40, true);

    expect($next->toIso8601String())->toBe('2026-10-28T12:00:30+00:00');
});

it('refuses an interval or a delay under one second', function (int $interval, int $delay): void {
    expect(fn () => CheckScheduleRules::nextCheckAt(utc('2026-10-28 12:00:00'), utc('2026-10-28 12:00:00'), $interval, $delay, false))
        ->toThrow(InvalidArgumentException::class);
})->with([[0, 20], [60, 0]]);

it('confirms a failure twenty seconds after the first one, then a recovery the same way', function (): void {
    $accepted = AcceptedStatusCodes::parse(['200-299']);
    $thresholds = new ConfirmationThresholds(2, 2);
    $snapshot = MonitorStateSnapshot::initial();
    $scheduled = utc('2026-10-28 12:00:00');
    $timeline = [];

    foreach ([200, 200, 500, 500, 500, 200, 200] as $status) {
        $checkedAt = $scheduled;
        $result = ProbeResult::responded($status, new ProbeTimings(totalMs: 50));
        $advance = MonitorStateRules::advance($snapshot, CheckOutcomeRules::evaluate($accepted, $result), $thresholds, $checkedAt);
        $scheduled = CheckScheduleRules::nextCheckAt($scheduled, $checkedAt, 60, 20, $advance->awaitsConfirmation);
        $snapshot = $advance->snapshot;
        $timeline[] = [$checkedAt->toTimeString(), $status, $snapshot->state->value, $advance->transition->value];
    }

    expect($timeline)->toBe([
        ['12:00:00', 200, 'pending', StateTransition::None->value],
        ['12:00:20', 200, 'up', StateTransition::None->value],
        ['12:01:20', 500, 'up', StateTransition::None->value],
        ['12:01:40', 500, 'down', StateTransition::WentDown->value],
        ['12:02:40', 500, 'down', StateTransition::None->value],
        ['12:03:40', 200, 'down', StateTransition::None->value],
        ['12:04:00', 200, 'up', StateTransition::Recovered->value],
    ])->and($snapshot->streakStartedAt?->toTimeString())->toBe('12:03:40');
});

function thresholdTimeline(ConfirmationThresholds $thresholds, array $statuses): array
{
    $accepted = AcceptedStatusCodes::parse(['200-299']);
    $snapshot = new MonitorStateSnapshot(MonitorState::Up, 0, 1, utc('2026-10-28 11:00:00'), utc('2026-10-28 11:00:00'));
    $scheduled = utc('2026-10-28 12:00:00');
    $timeline = [];

    foreach ($statuses as $status) {
        $checkedAt = $scheduled;
        $result = ProbeResult::responded($status, new ProbeTimings(totalMs: 50));
        $advance = MonitorStateRules::advance($snapshot, CheckOutcomeRules::evaluate($accepted, $result), $thresholds, $checkedAt);
        $scheduled = CheckScheduleRules::nextCheckAt($scheduled, $checkedAt, 60, 20, $advance->awaitsConfirmation);
        $snapshot = $advance->snapshot;
        $timeline[] = [$checkedAt->toTimeString(), $snapshot->state->value, $advance->transition->value];
    }

    return $timeline;
}

it('confirms at once and never rechecks with thresholds of one', function (): void {
    expect(thresholdTimeline(new ConfirmationThresholds(1, 1), [500, 500, 200, 200]))->toBe([
        ['12:00:00', 'down', StateTransition::WentDown->value],
        ['12:01:00', 'down', StateTransition::None->value],
        ['12:02:00', 'up', StateTransition::Recovered->value],
        ['12:03:00', 'up', StateTransition::None->value],
    ]);
});

it('rechecks until the tenth failure with a threshold of ten', function (): void {
    $timeline = thresholdTimeline(new ConfirmationThresholds(10, 2), array_fill(0, 11, 500));
    $waiting = array_map(
        fn (int $index): array => [utc('2026-10-28 12:00:00')->addSeconds(20 * $index)->toTimeString(), 'up', StateTransition::None->value],
        range(0, 8),
    );

    expect($timeline)->toBe([
        ...$waiting,
        ['12:03:00', 'down', StateTransition::WentDown->value],
        ['12:04:00', 'down', StateTransition::None->value],
    ]);
});

it('reads the failure and the recovery thresholds apart', function (): void {
    expect(thresholdTimeline(new ConfirmationThresholds(3, 1), [500, 500, 500, 200]))->toBe([
        ['12:00:00', 'up', StateTransition::None->value],
        ['12:00:20', 'up', StateTransition::None->value],
        ['12:00:40', 'down', StateTransition::WentDown->value],
        ['12:01:40', 'up', StateTransition::Recovered->value],
    ]);
});
