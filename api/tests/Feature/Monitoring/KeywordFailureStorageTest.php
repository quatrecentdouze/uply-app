<?php

declare(strict_types=1);

use App\Incidents\Models\Incident;
use App\Monitoring\Models\Monitor;
use App\Monitoring\Storage\CheckStorageMaintenance;
use Illuminate\Support\Facades\DB;

it('stores the keyword failures in the check results and as an incident cause', function (string $kind): void {
    $clock = freezeClock('2027-03-10 12:00:00');
    app(CheckStorageMaintenance::class)->run($clock->now());
    $monitor = Monitor::factory()->create();

    DB::table('check_results')->insert(['monitor_id' => $monitor->id, 'checked_at' => $clock->now(), 'outcome' => 'down', 'failure_kind' => $kind]);
    $incident = Incident::factory()->create(['monitor_id' => $monitor->id, 'cause_failure_kind' => $kind]);

    expect(DB::table('check_results')->where('monitor_id', $monitor->id)->value('failure_kind'))->toBe($kind)
        ->and($incident->fresh()?->cause_failure_kind)->toBe($kind);
})->with(['keyword_missing', 'keyword_found']);
