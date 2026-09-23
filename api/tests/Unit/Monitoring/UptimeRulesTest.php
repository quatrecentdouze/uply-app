<?php

declare(strict_types=1);

use App\Monitoring\Contracts\DailyUptime;
use App\Monitoring\Rules\UptimeRules;
use App\Monitoring\Values\CheckTotals;
use Carbon\CarbonImmutable;

it('gives the share of successful checks rounded down to a hundredth', function (int $up, int $total, float $percent): void {
    expect(UptimeRules::percent($up, $total))->toBe($percent);
})->with([
    'all up' => [1440, 1440, 100.0],
    'all down' => [0, 30, 0.0],
    'one failure in ten thousand never shows 100' => [9999, 10000, 99.99],
    'one failure in a hundred thousand' => [99999, 100000, 99.99],
    'a third' => [1, 3, 33.33],
    'two thirds' => [2, 3, 66.66],
]);

it('has no uptime without checks', function (): void {
    expect(UptimeRules::percent(0, 0))->toBeNull();
});

it('never goes above 100', function (): void {
    expect(UptimeRules::percent(12, 10))->toBe(100.0);
});

it('averages response times to the millisecond', function (int $sum, int $count, ?int $average): void {
    expect(UptimeRules::averageMs($sum, $count))->toBe($average);
})->with([
    [300, 3, 100],
    [301, 2, 151],
    [0, 0, null],
]);

it('starts the rolling day 24 hours before now in utc', function (): void {
    $start = UptimeRules::rollingDayStart(CarbonImmutable::parse('2027-03-10 12:00:00+02:00'));

    expect($start->toIso8601ZuluString())->toBe('2027-03-09T10:00:00Z');
});

it('builds a daily history with a null day when nothing was checked', function (): void {
    $history = UptimeRules::history(CarbonImmutable::parse('2027-03-10 15:00:00', 'UTC'), 3, [
        '2027-03-08' => new CheckTotals(10, 10, 0, 0, null, null),
        '2027-03-10' => new CheckTotals(10, 5, 0, 0, null, null),
        '2027-03-07' => new CheckTotals(100, 0, 0, 0, null, null),
    ]);

    expect($history->uptimePercent)->toBe(75.0)
        ->and(array_map(fn (DailyUptime $day): array => [$day->day->toDateString(), $day->uptimePercent], $history->days))->toBe([
            ['2027-03-08', 100.0],
            ['2027-03-09', null],
            ['2027-03-10', 50.0],
        ]);
});

it('has no uptime over days without checks', function (): void {
    $history = UptimeRules::history(CarbonImmutable::parse('2027-03-10', 'UTC'), 90, []);

    expect($history->uptimePercent)->toBeNull()
        ->and($history->days)->toHaveCount(90)
        ->and(UptimeRules::firstDay(CarbonImmutable::parse('2027-03-10 23:59:00', 'UTC'), 90)->toDateString())->toBe('2026-12-11');
});
