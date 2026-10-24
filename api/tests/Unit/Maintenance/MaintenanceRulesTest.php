<?php

declare(strict_types=1);

use App\Maintenance\Enums\MaintenanceState;
use App\Maintenance\Enums\PeriodProblem;
use App\Maintenance\Rules\MaintenanceRules;
use Carbon\CarbonImmutable;

function maintenanceMoment(string $time): CarbonImmutable
{
    return CarbonImmutable::parse("2027-03-10 {$time}", 'UTC');
}

function periodProblemsAt(string $startsAt, string $endsAt, bool $checksStart = true): array
{
    return MaintenanceRules::periodProblems(
        CarbonImmutable::parse($startsAt, 'UTC'),
        CarbonImmutable::parse($endsAt, 'UTC'),
        maintenanceMoment('12:00:00'),
        $checksStart,
        startGraceSeconds: 60,
        maxDaysAhead: 90,
        maxDurationHours: 168,
    );
}

it('is upcoming before its start, active from its start and ended from its end', function (string $now, MaintenanceState $state): void {
    expect(MaintenanceRules::state(maintenanceMoment('12:00:00'), maintenanceMoment('13:00:00'), maintenanceMoment($now)))->toBe($state)
        ->and(MaintenanceRules::covers(maintenanceMoment('12:00:00'), maintenanceMoment('13:00:00'), maintenanceMoment($now)))->toBe($state === MaintenanceState::Active);
})->with([
    'a second before the start' => ['11:59:59', MaintenanceState::Upcoming],
    'at the start' => ['12:00:00', MaintenanceState::Active],
    'a second before the end' => ['12:59:59', MaintenanceState::Active],
    'at the end' => ['13:00:00', MaintenanceState::Ended],
    'after the end' => ['15:00:00', MaintenanceState::Ended],
]);

it('keeps whole seconds in utc', function (): void {
    expect(MaintenanceRules::moment(CarbonImmutable::parse('2027-03-10 13:00:00.734+01:00'))->format('Y-m-d H:i:s.u e'))->toBe('2027-03-10 12:00:00.000000 UTC');
});

it('ends a window now, at least a second after its start', function (): void {
    expect(MaintenanceRules::endedAt(maintenanceMoment('12:00:00'), CarbonImmutable::parse('2027-03-10 12:30:15.5', 'UTC'))->toTimeString())->toBe('12:30:15')
        ->and(MaintenanceRules::endedAt(maintenanceMoment('12:00:00'), maintenanceMoment('12:00:00'))->toTimeString())->toBe('12:00:01');
});

it('accepts a window that starts now or later and lasts up to the longest duration', function (string $startsAt, string $endsAt): void {
    expect(periodProblemsAt($startsAt, $endsAt))->toBe([]);
})->with([
    'starting now' => ['2027-03-10 12:00:00', '2027-03-10 13:00:00'],
    'starting a minute ago' => ['2027-03-10 11:59:00', '2027-03-10 13:00:00'],
    'starting in 90 days' => ['2027-06-08 12:00:00', '2027-06-08 13:00:00'],
    'lasting 7 days' => ['2027-03-10 12:00:00', '2027-03-17 12:00:00'],
]);

it('names each problem of a period', function (string $startsAt, string $endsAt, array $problems): void {
    expect(periodProblemsAt($startsAt, $endsAt))->toBe($problems);
})->with([
    'start more than a minute ago' => ['2027-03-10 11:58:59', '2027-03-10 13:00:00', [PeriodProblem::StartInPast]],
    'start beyond 90 days' => ['2027-06-08 12:00:01', '2027-06-08 13:00:00', [PeriodProblem::StartTooFar]],
    'end at the start' => ['2027-03-10 12:30:00', '2027-03-10 12:30:00', [PeriodProblem::EndNotAfterStart]],
    'end before the start' => ['2027-03-10 12:30:00', '2027-03-10 12:10:00', [PeriodProblem::EndNotAfterStart]],
    'end now' => ['2027-03-10 11:59:30', '2027-03-10 12:00:00', [PeriodProblem::EndInPast]],
    'longer than 7 days' => ['2027-03-10 12:00:00', '2027-03-17 12:00:01', [PeriodProblem::TooLong]],
    'old start and end' => ['2027-03-09 10:00:00', '2027-03-09 11:00:00', [PeriodProblem::StartInPast, PeriodProblem::EndInPast]],
]);

it('leaves the start of a started window alone when it does not change', function (): void {
    expect(periodProblemsAt('2027-03-10 08:00:00', '2027-03-10 13:00:00', checksStart: false))->toBe([])
        ->and(periodProblemsAt('2027-03-10 08:00:00', '2027-03-10 11:00:00', checksStart: false))->toBe([PeriodProblem::EndInPast]);
});

it('ties start problems to the start field and the others to the end field', function (): void {
    expect(array_map(fn (PeriodProblem $problem): string => $problem->field(), PeriodProblem::cases()))
        ->toBe(['starts_at', 'starts_at', 'ends_at', 'ends_at', 'ends_at']);
});
