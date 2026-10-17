<?php

declare(strict_types=1);

use App\Monitoring\Enums\MonitorState;
use App\Monitoring\Events\MonitorRecoveredEvent;
use App\Monitoring\Events\MonitorWentDownEvent;
use App\Monitoring\Models\Monitor;
use App\Monitoring\Probe\HostResolver;
use App\Monitoring\Storage\CheckStorageMaintenance;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Support\FrozenClock;
use Tests\Support\StaticHostResolver;

beforeEach(function (): void {
    $this->clock = freezeClock('2027-03-10 12:00:00');
    app(CheckStorageMaintenance::class)->run($this->clock->now());
    app()->instance(HostResolver::class, new StaticHostResolver);
    Event::fake([MonitorWentDownEvent::class, MonitorRecoveredEvent::class]);
});

function thresholdMonitor(FrozenClock $clock, int $failureThreshold, int $recoveryThreshold): Monitor
{
    return Monitor::factory()->create([
        'url' => 'https://api.example.com/health',
        'state' => MonitorState::Up,
        'consecutive_successes' => 1,
        'failure_threshold' => $failureThreshold,
        'recovery_threshold' => $recoveryThreshold,
        'next_check_at' => $clock->now(),
    ]);
}

function checkTimeline(FrozenClock $clock, Monitor $monitor, array $times): array
{
    $timeline = [];
    foreach ($times as $time) {
        $clock->moveTo($clock->now()->setTimeFromTimeString($time));
        test()->artisan('checks:dispatch')->assertSuccessful();
        $fresh = $monitor->fresh();
        $timeline[] = [$time, $fresh?->state->value, $fresh?->next_check_at?->toTimeString()];
    }

    return $timeline;
}

it('confirms a failure and a recovery on the first result with thresholds of one', function (): void {
    Http::fake(['api.example.com/*' => Http::sequence()->push('boom', 500)->push('ok', 200)]);
    $monitor = thresholdMonitor($this->clock, 1, 1);

    expect(checkTimeline($this->clock, $monitor, ['12:00:00', '12:01:00']))->toBe([
        ['12:00:00', 'down', '12:01:00'],
        ['12:01:00', 'up', '12:02:00'],
    ]);
    Event::assertDispatchedOnce(MonitorWentDownEvent::class, fn (MonitorWentDownEvent $event): bool => $event->downSince->toTimeString() === '12:00:00'
        && $event->confirmedAt->toTimeString() === '12:00:00');
    Event::assertDispatchedOnce(MonitorRecoveredEvent::class, fn (MonitorRecoveredEvent $event): bool => $event->upSince->toTimeString() === '12:01:00');
});

it('rechecks until the threshold of the monitor is reached', function (): void {
    Http::fake(['api.example.com/*' => Http::sequence()->push('boom', 500)->push('boom', 500)->push('boom', 500)]);
    $monitor = thresholdMonitor($this->clock, 3, 2);

    expect(checkTimeline($this->clock, $monitor, ['12:00:00', '12:00:20', '12:00:40']))->toBe([
        ['12:00:00', 'up', '12:00:20'],
        ['12:00:20', 'up', '12:00:40'],
        ['12:00:40', 'down', '12:01:40'],
    ]);
    Event::assertDispatchedOnce(MonitorWentDownEvent::class, fn (MonitorWentDownEvent $event): bool => $event->downSince->toTimeString() === '12:00:00');
});
