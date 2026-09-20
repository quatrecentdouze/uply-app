<?php

declare(strict_types=1);

use App\Monitoring\Enums\StatsWindow;
use App\Monitoring\Rules\StatsRules;
use App\Monitoring\Values\CheckTotals;
use App\Monitoring\Values\HourlyTotals;
use App\Monitoring\Values\SeriesPoint;
use Carbon\CarbonImmutable;

function statsMoment(string $moment): CarbonImmutable
{
    return CarbonImmutable::parse($moment, 'UTC');
}

function hourOf(string $hourStart, int $total, int $up, int $sum = 0, int $count = 0, ?int $min = null, ?int $max = null): HourlyTotals
{
    return new HourlyTotals(statsMoment($hourStart), new CheckTotals($total, $up, $sum, $count, $min, $max));
}

function pointsOf(array $series): array
{
    return array_map(fn (SeriesPoint $point): array => [
        $point->start->toIso8601ZuluString(),
        $point->uptimePercent,
        $point->avgResponseMs,
    ], $series);
}

it('plans the buckets of each window in utc with the current one last', function (StatsWindow $window, string $first, int $hours, int $count, string $end): void {
    $plan = StatsRules::plan($window, CarbonImmutable::parse('2027-03-10 14:25:12+01:00'));

    expect($plan->firstStart->toIso8601ZuluString())->toBe($first)
        ->and($plan->bucketHours)->toBe($hours)
        ->and($plan->count)->toBe($count)
        ->and($plan->end()->toIso8601ZuluString())->toBe($end);
})->with([
    '24 buckets of one hour' => [StatsWindow::Day, '2027-03-09T14:00:00Z', 1, 24, '2027-03-10T14:00:00Z'],
    '28 buckets of six hours' => [StatsWindow::Week, '2027-03-03T18:00:00Z', 6, 28, '2027-03-10T18:00:00Z'],
    '30 buckets of one day' => [StatsWindow::Month, '2027-02-09T00:00:00Z', 24, 30, '2027-03-11T00:00:00Z'],
]);

it('fills every bucket and leaves a bucket without checks at null', function (): void {
    $plan = StatsRules::plan(StatsWindow::Week, statsMoment('2027-03-10 13:10:00'));

    $series = StatsRules::series($plan, [
        hourOf('2027-03-10 12:00', 60, 60, 6000, 60, 80, 140),
        hourOf('2027-03-10 13:00', 10, 5, 1500, 10, 90, 300),
        hourOf('2027-03-10 07:00', 4, 3, 0, 0),
        hourOf('2027-03-03 11:00', 9, 9, 900, 9),
        hourOf('2027-03-10 18:00', 9, 9, 900, 9),
    ]);

    expect($series)->toHaveCount(28)
        ->and(pointsOf($series)[0])->toBe(['2027-03-03T18:00:00Z', null, null])
        ->and(pointsOf($series)[26])->toBe(['2027-03-10T06:00:00Z', 75.0, null])
        ->and(pointsOf($series)[27])->toBe(['2027-03-10T12:00:00Z', 92.85, 107]);
});

it('adds up the hours inside the plan only', function (): void {
    $plan = StatsRules::plan(StatsWindow::Day, statsMoment('2027-03-10 13:10:00'));

    $totals = StatsRules::within($plan, [
        hourOf('2027-03-10 13:00', 10, 9, 1000, 9, 50, 400),
        hourOf('2027-03-09 14:00', 10, 10, 2000, 10, 20, 300),
        hourOf('2027-03-09 13:00', 100, 0),
    ]);

    expect($totals->checksTotal)->toBe(20)
        ->and($totals->checksUp)->toBe(19)
        ->and($totals->responseMsMin)->toBe(20)
        ->and($totals->responseMsMax)->toBe(400);
});

it('summarizes totals as uptime and response times', function (): void {
    $summary = StatsRules::summary(StatsRules::combine([
        new CheckTotals(3, 2, 300, 3, 50, 150),
        new CheckTotals(1, 1, 101, 1, 101, 101),
        new CheckTotals(1, 0, 0, 0, null, null),
    ]));

    expect($summary->checksTotal)->toBe(5)
        ->and($summary->uptimePercent)->toBe(60.0)
        ->and($summary->avgResponseMs)->toBe(100)
        ->and($summary->minResponseMs)->toBe(50)
        ->and($summary->maxResponseMs)->toBe(150);
});

it('has no uptime and no response time without checks', function (): void {
    $summary = StatsRules::summary(CheckTotals::none());

    expect($summary->checksTotal)->toBe(0)
        ->and($summary->uptimePercent)->toBeNull()
        ->and($summary->avgResponseMs)->toBeNull()
        ->and($summary->minResponseMs)->toBeNull();
});

it('refuses totals that do not add up', function (): void {
    expect(fn () => new CheckTotals(1, 2, 0, 0, null, null))->toThrow(InvalidArgumentException::class);
});
