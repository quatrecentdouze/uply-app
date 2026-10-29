<?php

declare(strict_types=1);

use App\Incidents\Events\IncidentOpenedEvent;
use App\Incidents\Events\IncidentResolvedEvent;
use App\Incidents\Models\Incident;
use App\Incidents\Models\IncidentEvent;
use App\Monitoring\Enums\FailureKind;
use App\Monitoring\Events\MonitorRecoveredEvent;
use App\Monitoring\Events\MonitorWentDownEvent;
use App\Monitoring\Models\Monitor;
use App\Monitoring\Probe\HostResolver;
use App\Monitoring\Storage\CheckStorageMaintenance;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Support\StaticHostResolver;

beforeEach(function (): void {
    $this->clock = freezeClock('2027-03-10 12:00:00');
    $this->monitor = Monitor::factory()->create(['url' => 'https://api.example.com/health', 'next_check_at' => $this->clock->now()]);
});

function wentDown(Monitor $monitor, string $since, string $confirmedAt, bool $flapping = false): MonitorWentDownEvent
{
    return new MonitorWentDownEvent(
        monitorId: $monitor->id,
        organizationId: $monitor->organization_id,
        downSince: CarbonImmutable::parse($since, 'UTC'),
        confirmedAt: CarbonImmutable::parse($confirmedAt, 'UTC'),
        failureKind: FailureKind::HttpStatus,
        httpStatus: 503,
        errorMessage: null,
        flapping: $flapping,
    );
}

function recovered(Monitor $monitor, string $since, string $confirmedAt, bool $flapping = false): MonitorRecoveredEvent
{
    return new MonitorRecoveredEvent($monitor->id, $monitor->organization_id, CarbonImmutable::parse($since, 'UTC'), CarbonImmutable::parse($confirmedAt, 'UTC'), $flapping);
}

it('opens an incident at the first failure of the streak', function (): void {
    Event::fake([IncidentOpenedEvent::class]);

    event(wentDown($this->monitor, '2027-03-10 12:01:20', '2027-03-10 12:01:40'));

    $incident = Incident::query()->sole();
    expect($incident->monitor_id)->toBe($this->monitor->id)
        ->and($incident->organization_id)->toBe($this->monitor->organization_id)
        ->and($incident->started_at->toIso8601String())->toBe('2027-03-10T12:01:20+00:00')
        ->and($incident->resolved_at)->toBeNull()
        ->and($incident->cause_failure_kind)->toBe('http_status')
        ->and($incident->cause_http_status)->toBe(503);
    $opened = IncidentEvent::query()->sole();
    expect($opened->kind->value)->toBe('opened')
        ->and($opened->data)->toEqual(['failure_kind' => 'http_status', 'http_status' => 503, 'message' => null]);
    Event::assertDispatched(IncidentOpenedEvent::class, fn (IncidentOpenedEvent $event): bool => $event->incidentId === $incident->id
        && $event->startedAt->toTimeString() === '12:01:20'
        && $event->causeFailureKind === 'http_status'
        && $event->causeHttpStatus === 503);
});

it('resolves the open incident at the first success of the recovery', function (): void {
    Event::fake([IncidentResolvedEvent::class]);
    event(wentDown($this->monitor, '2027-03-10 12:01:20', '2027-03-10 12:01:40'));

    event(recovered($this->monitor, '2027-03-10 12:05:00', '2027-03-10 12:05:20'));

    $incident = Incident::query()->sole();
    expect($incident->resolved_at?->toIso8601String())->toBe('2027-03-10T12:05:00+00:00');
    $timeline = IncidentEvent::query()->orderBy('occurred_at')->get();
    expect($timeline->map(fn (IncidentEvent $event): string => $event->kind->value)->all())->toBe(['opened', 'resolved'])
        ->and($timeline->last()?->data)->toBe(['duration_seconds' => 220]);
    Event::assertDispatched(IncidentResolvedEvent::class, fn (IncidentResolvedEvent $event): bool => $event->incidentId === $incident->id
        && $event->durationSeconds === 220
        && $event->resolvedAt->toTimeString() === '12:05:00');
});

it('keeps opening and resolving incidents of a flapping monitor and tells it to the alerts', function (bool $flapping): void {
    Event::fake([IncidentOpenedEvent::class, IncidentResolvedEvent::class]);

    event(wentDown($this->monitor, '2027-03-10 12:01:20', '2027-03-10 12:01:40', $flapping));
    event(recovered($this->monitor, '2027-03-10 12:05:00', '2027-03-10 12:05:20', $flapping));

    expect(Incident::query()->sole()->resolved_at)->not->toBeNull();
    Event::assertDispatched(IncidentOpenedEvent::class, fn (IncidentOpenedEvent $event): bool => $event->monitorFlapping === $flapping);
    Event::assertDispatched(IncidentResolvedEvent::class, fn (IncidentResolvedEvent $event): bool => $event->monitorFlapping === $flapping);
})->with([true, false]);

it('keeps a single open incident per monitor', function (): void {
    event(wentDown($this->monitor, '2027-03-10 12:01:20', '2027-03-10 12:01:40'));
    event(wentDown($this->monitor, '2027-03-10 12:02:20', '2027-03-10 12:02:40'));

    expect(Incident::query()->count())->toBe(1)
        ->and(IncidentEvent::query()->count())->toBe(1);
});

it('ignores a recovery without open incident', function (): void {
    Event::fake([IncidentResolvedEvent::class]);

    event(recovered($this->monitor, '2027-03-10 12:05:00', '2027-03-10 12:05:20'));

    expect(Incident::query()->count())->toBe(0);
    Event::assertNotDispatched(IncidentResolvedEvent::class);
});

it('refuses a second open incident at the database level', function (): void {
    Incident::factory()->create(['monitor_id' => $this->monitor->id]);

    expect(fn () => Incident::factory()->create(['monitor_id' => $this->monitor->id]))->toThrow(QueryException::class);
});

it('opens and resolves two successive outages of the same monitor', function (): void {
    event(wentDown($this->monitor, '2027-03-10 12:01:20', '2027-03-10 12:01:40'));
    event(recovered($this->monitor, '2027-03-10 12:05:00', '2027-03-10 12:05:20'));
    event(wentDown($this->monitor, '2027-03-10 13:00:00', '2027-03-10 13:00:20'));
    event(recovered($this->monitor, '2027-03-10 13:10:00', '2027-03-10 13:10:20'));

    $incidents = Incident::query()->orderBy('started_at')->get();
    expect($incidents)->toHaveCount(2)
        ->and($incidents->map(fn (Incident $incident): string => $incident->started_at->toTimeString().'-'.$incident->resolved_at?->toTimeString())->all())
        ->toBe(['12:01:20-12:05:00', '13:00:00-13:10:00'])
        ->and(IncidentEvent::query()->count())->toBe(4);
});

it('opens and resolves incidents from real checks', function (): void {
    app(CheckStorageMaintenance::class)->run($this->clock->now());
    app()->instance(HostResolver::class, new StaticHostResolver);
    Http::fake(['api.example.com/*' => Http::sequence()
        ->push('ok', 200)->push('ok', 200)
        ->push('down', 502)->push('down', 502)
        ->push('ok', 200)->push('ok', 200)
        ->push('down', 502)->push('down', 502)
        ->push('ok', 200)->push('ok', 200)]);

    foreach (['12:00:00', '12:00:20', '12:01:20', '12:01:40', '12:02:40', '12:03:00', '12:04:00', '12:04:20', '12:05:20', '12:05:40'] as $time) {
        $this->clock->moveTo($this->clock->now()->setTimeFromTimeString($time));
        $this->artisan('checks:dispatch')->assertSuccessful();
    }

    $incidents = Incident::query()->orderBy('started_at')->get();
    expect($incidents->map(fn (Incident $incident): array => [
        $incident->started_at->toTimeString(),
        $incident->resolved_at?->toTimeString(),
        $incident->cause_failure_kind,
        $incident->cause_http_status,
    ])->all())->toBe([
        ['12:01:20', '12:02:40', 'http_status', 502],
        ['12:04:00', '12:05:20', 'http_status', 502],
    ]);
});
