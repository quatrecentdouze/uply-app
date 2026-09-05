<?php

declare(strict_types=1);

use App\Monitoring\Enums\FailureKind;
use App\Monitoring\Enums\MonitorState;
use App\Monitoring\Events\CheckCompletedEvent;
use App\Monitoring\Events\MonitorRecoveredEvent;
use App\Monitoring\Events\MonitorWentDownEvent;
use App\Monitoring\Jobs\RunCheckJob;
use App\Monitoring\Models\Monitor;
use App\Monitoring\Probe\HostResolver;
use App\Monitoring\Storage\CheckStorageMaintenance;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Support\FrozenClock;
use Tests\Support\StaticHostResolver;

beforeEach(function (): void {
    $this->clock = freezeClock('2027-03-10 12:00:00');
    app(CheckStorageMaintenance::class)->run($this->clock->now());
    app()->instance(HostResolver::class, new StaticHostResolver(['internal.example.com' => ['10.0.0.8']]));
    $this->monitor = Monitor::factory()->create([
        'url' => 'https://api.example.com/health',
        'next_check_at' => $this->clock->now(),
    ]);
});

function runDispatchAt(FrozenClock $clock, string $time): void
{
    $clock->moveTo($clock->now()->setTimeFromTimeString($time));
    test()->artisan('checks:dispatch')->assertSuccessful();
}

it('confirms a failure on the second check and a recovery on the second success', function (): void {
    Event::fake([CheckCompletedEvent::class, MonitorWentDownEvent::class, MonitorRecoveredEvent::class]);
    Http::fake(['api.example.com/*' => Http::sequence()
        ->push('ok', 200)->push('ok', 200)
        ->push('boom', 500)->push('boom', 500)
        ->push('ok', 200)->push('ok', 200)]);
    $states = [];

    foreach (['12:00:00', '12:00:20', '12:01:20', '12:01:40', '12:02:40', '12:03:00'] as $time) {
        runDispatchAt($this->clock, $time);
        $monitor = $this->monitor->fresh();
        $states[] = [$time, $monitor?->state->value, $monitor?->next_check_at?->toTimeString()];
    }

    expect($states)->toBe([
        ['12:00:00', 'pending', '12:00:20'],
        ['12:00:20', 'up', '12:01:20'],
        ['12:01:20', 'up', '12:01:40'],
        ['12:01:40', 'down', '12:02:40'],
        ['12:02:40', 'down', '12:03:00'],
        ['12:03:00', 'up', '12:04:00'],
    ]);
    Event::assertDispatchedTimes(CheckCompletedEvent::class, 6);
    Event::assertDispatched(MonitorWentDownEvent::class, fn (MonitorWentDownEvent $event): bool => $event->monitorId === $this->monitor->id
        && $event->organizationId === $this->monitor->organization_id
        && $event->downSince->toTimeString() === '12:01:20'
        && $event->confirmedAt->toTimeString() === '12:01:40'
        && $event->failureKind === FailureKind::HttpStatus
        && $event->httpStatus === 500);
    Event::assertDispatchedOnce(MonitorWentDownEvent::class);
    Event::assertDispatched(MonitorRecoveredEvent::class, fn (MonitorRecoveredEvent $event): bool => $event->upSince->toTimeString() === '12:02:40'
        && $event->confirmedAt->toTimeString() === '12:03:00');
    Event::assertDispatchedOnce(MonitorRecoveredEvent::class);
});

it('stores every result and the hourly stats of the monitor', function (): void {
    Http::fake(['api.example.com/*' => Http::sequence()->push('ok', 200)->push('boom', 503)->push('ok', 204)]);

    foreach (['12:00:00', '12:00:20', '12:00:40'] as $time) {
        runDispatchAt($this->clock, $time);
    }

    $results = DB::table('check_results')->orderBy('checked_at')->get(['checked_at', 'outcome', 'failure_kind', 'http_status']);
    expect($results->map(fn (object $row): array => (array) $row)->all())->toBe([
        ['checked_at' => '2027-03-10 12:00:00+00', 'outcome' => 'up', 'failure_kind' => null, 'http_status' => 200],
        ['checked_at' => '2027-03-10 12:00:20+00', 'outcome' => 'down', 'failure_kind' => 'http_status', 'http_status' => 503],
        ['checked_at' => '2027-03-10 12:00:40+00', 'outcome' => 'up', 'failure_kind' => null, 'http_status' => 204],
    ]);
    $stats = DB::table('monitor_hourly_stats')->sole();
    expect($stats->hour_start)->toBe('2027-03-10 12:00:00+00')
        ->and($stats->checks_total)->toBe(3)
        ->and($stats->checks_up)->toBe(2)
        ->and($stats->response_ms_count)->toBe(3)
        ->and($stats->response_ms_min)->toBeLessThanOrEqual($stats->response_ms_max);
});

it('never claims the same slot twice', function (): void {
    Http::fake(['api.example.com/*' => Http::response('ok', 200)]);

    $this->artisan('checks:dispatch')->expectsOutput('dispatched=1')->assertSuccessful();
    $this->artisan('checks:dispatch')->expectsOutput('dispatched=0')->assertSuccessful();

    Http::assertSentCount(1);
    expect(DB::table('check_results')->count())->toBe(1);
});

it('drops a second job for a slot that was already checked', function (): void {
    Http::fake(['api.example.com/*' => Http::response('ok', 200)]);
    $scheduledAt = $this->clock->now();

    dispatch_sync(new RunCheckJob($this->monitor->id, $scheduledAt));
    dispatch_sync(new RunCheckJob($this->monitor->id, $scheduledAt));

    Http::assertSentCount(1);
    expect(DB::table('check_results')->count())->toBe(1);
});

it('never claims a paused monitor', function (): void {
    Http::fake();
    $this->monitor->update(['is_paused' => true]);

    $this->artisan('checks:dispatch')->expectsOutput('dispatched=0')->assertSuccessful();
    dispatch_sync(new RunCheckJob($this->monitor->id, $this->clock->now()));

    Http::assertNothingSent();
    expect(DB::table('check_results')->count())->toBe(0);
});

it('records a blocked target without sending anything', function (): void {
    Http::fake();
    $this->monitor->update(['url' => 'https://internal.example.com/admin']);

    $this->artisan('checks:dispatch')->assertSuccessful();

    Http::assertNothingSent();
    $result = DB::table('check_results')->sole();
    expect($result->failure_kind)->toBe('blocked_target')
        ->and($result->error_message)->toContain('10.0.0.8');
});

it('jumps over the slots missed while the workers were stopped', function (): void {
    Http::fake(['api.example.com/*' => Http::response('ok', 200)]);
    $this->monitor->forceFill(['state' => MonitorState::Up, 'next_check_at' => $this->clock->now()->subHour()])->save();

    $this->artisan('checks:dispatch')->assertSuccessful();

    expect($this->monitor->fresh()?->next_check_at?->toIso8601String())->toBe('2027-03-10T12:01:00+00:00');
});

it('sends the configured user agent through the http client', function (): void {
    Http::fake(['api.example.com/*' => Http::response('ok', 200)]);

    $this->artisan('checks:dispatch')->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://api.example.com/health'
        && $request->hasHeader('User-Agent', 'Uply/1.0'));
});
