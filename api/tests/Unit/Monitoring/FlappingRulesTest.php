<?php

declare(strict_types=1);

use App\Monitoring\Enums\FlappingTurn;
use App\Monitoring\Rules\FlappingRules;
use App\Monitoring\Values\FlappingState;
use Carbon\CarbonImmutable;

function flappingMoment(string $time): CarbonImmutable
{
    return CarbonImmutable::parse("2027-03-10 {$time}", 'UTC');
}

function changesAt(string ...$times): array
{
    return array_map(fn (string $time): CarbonImmutable => flappingMoment($time), $times);
}

function transitionAt(FlappingState $state, string $time): array
{
    $step = FlappingRules::afterTransition($state, flappingMoment($time), changes: 5, windowMinutes: 30);

    return [$step->turn, $step->state->flappingSince?->toTimeString(), array_map(fn (CarbonImmutable $change): string => $change->toTimeString(), $step->state->stateChanges)];
}

it('starts flapping on the fifth change within 30 minutes, at that change', function (): void {
    $state = new FlappingState(null, changesAt('12:00:00', '12:05:00', '12:10:00', '12:20:00'));

    expect(transitionAt($state, '12:29:59'))->toBe([FlappingTurn::Started, '12:29:59', ['12:00:00', '12:05:00', '12:10:00', '12:20:00', '12:29:59']])
        ->and(transitionAt($state, '12:30:00'))->toBe([FlappingTurn::Started, '12:30:00', ['12:00:00', '12:05:00', '12:10:00', '12:20:00', '12:30:00']]);
});

it('does not flap on five changes spread over more than 30 minutes', function (): void {
    $state = new FlappingState(null, changesAt('12:00:00', '12:05:00', '12:10:00', '12:20:00'));

    expect(transitionAt($state, '12:30:01'))->toBe([FlappingTurn::None, null, ['12:00:00', '12:05:00', '12:10:00', '12:20:00', '12:30:01']]);
});

it('keeps only the last changes it needs', function (): void {
    $state = new FlappingState(null, changesAt('11:00:00', '11:10:00', '11:20:00', '11:30:00', '11:40:00'));

    expect(transitionAt($state, '12:00:00'))->toBe([FlappingTurn::None, null, ['11:10:00', '11:20:00', '11:30:00', '11:40:00', '12:00:00']])
        ->and(transitionAt(FlappingState::none(), '12:00:00'))->toBe([FlappingTurn::None, null, ['12:00:00']]);
});

it('keeps flapping without a new start while the monitor still changes', function (): void {
    $state = new FlappingState(flappingMoment('12:04:00'), changesAt('12:00:00', '12:01:00', '12:02:00', '12:03:00', '12:04:00'));

    expect(transitionAt($state, '12:05:00'))->toBe([FlappingTurn::None, '12:04:00', ['12:01:00', '12:02:00', '12:03:00', '12:04:00', '12:05:00']]);
});

it('ends flapping once the last change is 30 minutes old and tells when the episode started', function (): void {
    $state = new FlappingState(flappingMoment('12:04:00'), changesAt('12:00:00', '12:01:00', '12:02:00', '12:03:00', '12:10:00'));

    $tooSoon = FlappingRules::afterCheck($state, flappingMoment('12:39:59'), stableMinutes: 30);
    $stable = FlappingRules::afterCheck($state, flappingMoment('12:40:00'), stableMinutes: 30);

    expect($tooSoon->turn)->toBe(FlappingTurn::None)
        ->and($tooSoon->state->flappingSince?->toTimeString())->toBe('12:04:00')
        ->and($stable->turn)->toBe(FlappingTurn::Ended)
        ->and($stable->state->isFlapping())->toBeFalse()
        ->and($stable->episodeStartedAt?->toTimeString())->toBe('12:04:00')
        ->and($stable->state->stateChanges)->toHaveCount(5);
});

it('never ends what is not flapping', function (): void {
    $step = FlappingRules::afterCheck(new FlappingState(null, changesAt('10:00:00')), flappingMoment('12:00:00'), stableMinutes: 30);

    expect($step->turn)->toBe(FlappingTurn::None)
        ->and($step->state->isFlapping())->toBeFalse();
});

it('does not start again right after an end with old changes', function (): void {
    $ended = new FlappingState(null, changesAt('12:00:00', '12:01:00', '12:02:00', '12:03:00', '12:04:00'));

    expect(transitionAt($ended, '12:35:00')[0])->toBe(FlappingTurn::None);
});

it('stores the changes as utc times in order', function (): void {
    $state = FlappingState::fromStored(flappingMoment('12:04:00'), ['2027-03-10T13:01:00+01:00', '2027-03-10T11:00:00Z', 7]);

    expect($state->toStored())->toBe(['2027-03-10T11:00:00Z', '2027-03-10T12:01:00Z'])
        ->and($state->lastChangeAt()?->toTimeString())->toBe('12:01:00')
        ->and(FlappingState::none()->lastChangeAt())->toBeNull();
});
