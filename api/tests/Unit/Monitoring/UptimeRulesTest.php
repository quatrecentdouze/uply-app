<?php

declare(strict_types=1);

use App\Monitoring\Rules\UptimeRules;

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
