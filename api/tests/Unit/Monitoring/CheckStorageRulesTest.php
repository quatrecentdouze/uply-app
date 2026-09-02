<?php

declare(strict_types=1);

use App\Monitoring\Rules\CheckStorageRules;
use Carbon\CarbonImmutable;

it('names a partition after its utc day', function (): void {
    expect(CheckStorageRules::partitionName(CarbonImmutable::parse('2026-10-28 23:59:59', 'UTC')))->toBe('check_results_20261028')
        ->and(CheckStorageRules::partitionName(CarbonImmutable::parse('2026-10-29 00:30:00', 'Europe/Paris')))->toBe('check_results_20261028')
        ->and(CheckStorageRules::partitionDay('check_results_20261028')?->toDateString())->toBe('2026-10-28')
        ->and(CheckStorageRules::partitionDay('check_results_20261332'))->toBeNull()
        ->and(CheckStorageRules::partitionDay('monitors'))->toBeNull();
});

it('plans today and the days ahead that do not exist yet', function (): void {
    $plan = CheckStorageRules::partitionPlan(
        CarbonImmutable::parse('2026-10-28 15:00:00', 'UTC'),
        ['check_results_20261028', 'check_results_20261030'],
        3,
        30,
    );

    expect(array_map(fn (CarbonImmutable $day): string => $day->toDateString(), $plan->daysToCreate))->toBe(['2026-10-29', '2026-10-31'])
        ->and($plan->partitionsToDrop)->toBe([]);
});

it('drops the partitions older than the retention and keeps the boundary day', function (): void {
    $plan = CheckStorageRules::partitionPlan(
        CarbonImmutable::parse('2026-10-28 00:10:00', 'UTC'),
        ['check_results_20260927', 'check_results_20260926', 'check_results_20260928', 'check_results_20261028', 'stray_table'],
        0,
        30,
    );

    expect($plan->partitionsToDrop)->toBe(['check_results_20260926', 'check_results_20260927'])
        ->and($plan->daysToCreate)->toBe([]);
});

it('cuts hourly stats at the retention from the current hour', function (): void {
    expect(CheckStorageRules::hourlyStatsCutoff(CarbonImmutable::parse('2026-10-28 15:42:10', 'UTC'), 400)->toIso8601String())
        ->toBe('2025-09-23T15:00:00+00:00');
});
