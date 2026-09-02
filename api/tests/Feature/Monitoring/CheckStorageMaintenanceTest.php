<?php

declare(strict_types=1);

use App\Monitoring\Models\Monitor;
use App\Monitoring\Rules\CheckStorageRules;
use App\Monitoring\Storage\CheckStorageMaintenance;
use App\Shared\SystemClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function insertCheckResult(string $monitorId, string $checkedAt): void
{
    DB::table('check_results')->insert([
        'monitor_id' => $monitorId,
        'checked_at' => $checkedAt,
        'outcome' => 'up',
        'http_status' => 200,
        'response_ms' => 42,
    ]);
}

it('creates the partitions of today and the coming days during the migration', function (): void {
    $today = (new SystemClock)->now();
    $existing = app(CheckStorageMaintenance::class)->existingPartitions();

    foreach (range(0, 3) as $offset) {
        expect($existing)->toContain(CheckStorageRules::partitionName($today->addDays($offset)));
    }
});

it('stores results only inside an existing daily partition', function (): void {
    freezeClock('2027-03-10 08:00:00');
    $this->artisan('monitoring:maintain')
        ->expectsOutputToContain('partitions_created=4 ')
        ->assertSuccessful();
    $monitor = Monitor::factory()->create();

    insertCheckResult($monitor->id, '2027-03-13 23:59:59+00');

    expect(DB::table('check_results_20270313')->count())->toBe(1)
        ->and(fn () => insertCheckResult($monitor->id, '2027-03-14 00:00:00+00'))->toThrow(QueryException::class);
});

it('drops the partitions past the retention and keeps the recent ones', function (): void {
    $clock = freezeClock('2027-03-10 08:00:00');
    $maintenance = app(CheckStorageMaintenance::class);
    $maintenance->run($clock->now());

    $clock->moveTo(CarbonImmutable::parse('2027-04-11 08:00:00', 'UTC'));
    $report = $maintenance->run($clock->now());
    $existing = $maintenance->existingPartitions();

    expect($existing)->not->toContain('check_results_20270310')
        ->and($existing)->not->toContain('check_results_20270311')
        ->and($existing)->toContain('check_results_20270312')
        ->and($existing)->toContain('check_results_20270413')
        ->and($report->partitionsDropped)->toBeGreaterThanOrEqual(2);
});

it('purges hourly stats older than their retention', function (): void {
    $clock = freezeClock('2027-03-10 08:30:00');
    $monitor = Monitor::factory()->create();
    foreach (['2026-02-01 07:00:00+00', '2026-02-05 09:00:00+00'] as $hour) {
        DB::table('monitor_hourly_stats')->insert([
            'monitor_id' => $monitor->id,
            'hour_start' => $hour,
            'checks_total' => 60,
            'checks_up' => 60,
            'response_ms_sum' => 6000,
            'response_ms_count' => 60,
        ]);
    }

    $report = app(CheckStorageMaintenance::class)->run($clock->now());

    expect($report->hourlyStatsPurged)->toBe(1)
        ->and(DB::table('monitor_hourly_stats')->pluck('hour_start')->all())->toBe(['2026-02-05 09:00:00+00']);
});
