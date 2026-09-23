<?php

declare(strict_types=1);

use App\Incidents\Contracts\IncidentHistory;
use App\Incidents\Contracts\IncidentSummary;
use App\Incidents\Models\Incident;
use App\Monitoring\Models\Monitor;
use Carbon\CarbonImmutable;

it('gives other families the incidents of some monitors that touch a window, newest first', function (): void {
    $now = CarbonImmutable::parse('2027-03-10 12:00:00', 'UTC');
    $monitor = Monitor::factory()->create();
    $other = Monitor::factory()->create();
    $old = Incident::factory()->create(['monitor_id' => $monitor->id, 'started_at' => $now->subDays(20), 'resolved_at' => $now->subDays(19)]);
    $endedInside = Incident::factory()->create(['monitor_id' => $monitor->id, 'started_at' => $now->subDays(15), 'resolved_at' => $now->subDays(13)]);
    $recent = Incident::factory()->create(['monitor_id' => $monitor->id, 'started_at' => $now->subDays(2), 'resolved_at' => $now->subDays(2)->addSeconds(90)]);
    $open = Incident::factory()->create(['monitor_id' => $other->id, 'started_at' => $now->subDays(30)]);
    Incident::factory()->create(['started_at' => $now->subHour()]);

    $summaries = app(IncidentHistory::class)->since([$monitor->id, $other->id], $now->subDays(14), 10);

    expect(array_map(fn (IncidentSummary $incident): string => $incident->id, $summaries))->toBe([$recent->id, $endedInside->id, $open->id])
        ->and($summaries[0]->monitorId)->toBe($monitor->id)
        ->and($summaries[0]->durationSeconds)->toBe(90)
        ->and($summaries[2]->resolvedAt)->toBeNull()
        ->and($summaries[2]->durationSeconds)->toBeNull()
        ->and(array_map(fn (IncidentSummary $incident): string => $incident->id, $summaries))->not->toContain($old->id);
});

it('stops at the limit and knows no monitor', function (): void {
    $now = CarbonImmutable::parse('2027-03-10 12:00:00', 'UTC');
    $monitor = Monitor::factory()->create();
    Incident::factory()->create(['monitor_id' => $monitor->id, 'started_at' => $now->subDay(), 'resolved_at' => $now->subDay()->addMinute()]);
    Incident::factory()->create(['monitor_id' => $monitor->id, 'started_at' => $now->subHour()]);

    expect(app(IncidentHistory::class)->since([$monitor->id], $now->subDays(14), 1))->toHaveCount(1)
        ->and(app(IncidentHistory::class)->since([], $now->subDays(14), 10))->toBe([]);
});
