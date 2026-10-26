<?php

declare(strict_types=1);

use App\Monitoring\Models\Monitor;
use App\Monitoring\Storage\CheckStorageMaintenance;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $clock = freezeClock('2027-03-10 12:00:00');
    app(CheckStorageMaintenance::class)->run($clock->now());
    $this->monitor = Monitor::factory()->create();
});

it('keeps a check result outside maintenance unless told otherwise, in every daily partition', function (): void {
    DB::table('check_results')->insert(['monitor_id' => $this->monitor->id, 'checked_at' => '2027-03-10 12:00:00+00', 'outcome' => 'up']);
    DB::table('check_results')->insert(['monitor_id' => $this->monitor->id, 'checked_at' => '2027-03-11 12:00:00+00', 'outcome' => 'up', 'in_maintenance' => true]);

    expect(DB::table('check_results')->orderBy('checked_at')->pluck('in_maintenance')->all())->toBe([false, true]);
});

it('starts the maintenance count of an hour at zero and never lets it go negative', function (): void {
    $hour = ['monitor_id' => $this->monitor->id, 'hour_start' => '2027-03-10 12:00:00+00', 'checks_total' => 1, 'checks_up' => 1, 'response_ms_sum' => 10, 'response_ms_count' => 1];
    DB::table('monitor_hourly_stats')->insert($hour);

    expect(DB::table('monitor_hourly_stats')->value('checks_maintenance'))->toBe(0)
        ->and(fn () => DB::table('monitor_hourly_stats')->update(['checks_maintenance' => -1]))->toThrow(QueryException::class);
});

it('refuses a check result without a maintenance flag', function (): void {
    expect(fn () => DB::table('check_results')->insert(['monitor_id' => $this->monitor->id, 'checked_at' => '2027-03-10 12:00:00+00', 'outcome' => 'up', 'in_maintenance' => null]))
        ->toThrow(QueryException::class);
});
