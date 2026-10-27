<?php

declare(strict_types=1);

use App\Monitoring\Contracts\DailyUptime;
use App\Monitoring\Contracts\UptimeQuery;
use App\Monitoring\Models\Monitor;
use Carbon\CarbonImmutable;
use Tests\Support\CheckHistory;

it('gives other families the daily uptime of many monitors from the hourly stats', function (): void {
    $today = CarbonImmutable::parse('2027-03-10 12:00:00', 'UTC');
    $api = Monitor::factory()->create();
    $docs = Monitor::factory()->create();
    CheckHistory::hour($api->id, $today->subDays(2)->setTime(23, 0), 60, 60);
    CheckHistory::hour($api->id, $today->setTime(0, 0), 60, 30);
    CheckHistory::hour($api->id, $today->setTime(11, 0), 60, 60);
    CheckHistory::hour($api->id, $today->subDays(3)->setTime(12, 0), 60, 0);
    CheckHistory::hour($docs->id, $today->subDay()->setTime(5, 0), 10, 9);
    CheckHistory::hour($docs->id, $today->setTime(3, 0), 0, 0, maintenance: 120);
    CheckHistory::hour($docs->id, $today->subDay()->setTime(6, 0), 0, 0);

    $histories = app(UptimeQuery::class)->daily([$api->id, $docs->id], $today, 3);
    $days = fn (string $id): array => array_map(fn (DailyUptime $day): array => [$day->day->toDateString(), $day->uptimePercent], $histories[$id]->days);

    expect(array_keys($histories))->toBe([$api->id, $docs->id])
        ->and($histories[$api->id]->uptimePercent)->toBe(83.33)
        ->and($days($api->id))->toBe([['2027-03-08', 100.0], ['2027-03-09', null], ['2027-03-10', 75.0]])
        ->and($histories[$docs->id]->uptimePercent)->toBe(90.0)
        ->and($days($docs->id))->toBe([['2027-03-08', null], ['2027-03-09', 90.0], ['2027-03-10', null]])
        ->and(array_map(fn (DailyUptime $day): bool => $day->maintenance, $histories[$docs->id]->days))->toBe([false, false, true])
        ->and(array_map(fn (DailyUptime $day): bool => $day->maintenance, $histories[$api->id]->days))->toBe([false, false, false])
        ->and(app(UptimeQuery::class)->daily([], $today, 90))->toBe([]);
});
