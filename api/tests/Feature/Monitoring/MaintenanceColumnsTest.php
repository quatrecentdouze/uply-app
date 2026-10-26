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

it('refuses a check result without a maintenance flag', function (): void {
    expect(fn () => DB::table('check_results')->insert(['monitor_id' => $this->monitor->id, 'checked_at' => '2027-03-10 12:00:00+00', 'outcome' => 'up', 'in_maintenance' => null]))
        ->toThrow(QueryException::class);
});
