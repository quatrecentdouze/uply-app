<?php

declare(strict_types=1);

use App\Maintenance\Models\MaintenanceWindow;
use App\Maintenance\Models\MaintenanceWindowMonitor;
use App\Monitoring\Enums\MonitorState;
use App\Monitoring\Events\MonitorFlappingEndedEvent;
use App\Monitoring\Events\MonitorFlappingStartedEvent;
use App\Monitoring\Events\MonitorRecoveredEvent;
use App\Monitoring\Events\MonitorWentDownEvent;
use App\Monitoring\Models\Monitor;
use App\Monitoring\Probe\Probe;
use App\Monitoring\Storage\CheckStorageMaintenance;
use App\Monitoring\Values\ProbeRequest;
use App\Monitoring\Values\ProbeResult;
use App\Monitoring\Values\ProbeTimings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->clock = freezeClock('2027-03-10 12:00:00');
    app(CheckStorageMaintenance::class)->run($this->clock->now());
    $this->probe = new class implements Probe
    {
        public int $status = 200;

        public function run(ProbeRequest $request): ProbeResult
        {
            return ProbeResult::responded($this->status, new ProbeTimings(totalMs: 40));
        }
    };
    app()->instance(Probe::class, $this->probe);
    Event::fake([MonitorFlappingStartedEvent::class, MonitorFlappingEndedEvent::class, MonitorWentDownEvent::class, MonitorRecoveredEvent::class]);
    $this->monitor = Monitor::factory()->create(['interval_seconds' => 30, 'failure_threshold' => 1, 'recovery_threshold' => 1, 'next_check_at' => $this->clock->now()]);
    Monitor::query()->whereKey($this->monitor->id)->update(['state' => MonitorState::Up]);
});

function checkFlappingAt(object $test, string $time, int $status): Monitor
{
    $test->probe->status = $status;
    $test->clock->moveTo(CarbonImmutable::parse("2027-03-10 {$time}", 'UTC'));
    Monitor::query()->whereKey($test->monitor->id)->update(['next_check_at' => $test->clock->now()]);
    test()->artisan('checks:dispatch')->assertSuccessful();

    return $test->monitor->fresh() ?? throw new LogicException('the monitor is gone');
}

it('starts flapping once on the fifth change and flags the transitions from then on', function (): void {
    $flapping = [];
    foreach ([['12:00:00', 500], ['12:00:30', 200], ['12:01:00', 500], ['12:01:30', 200], ['12:02:00', 500], ['12:02:30', 200], ['12:03:00', 500]] as [$time, $status]) {
        $flapping[] = checkFlappingAt($this, $time, $status)->flapping_since?->toTimeString();
    }

    expect($flapping)->toBe([null, null, null, null, '12:02:00', '12:02:00', '12:02:00']);
    Event::assertDispatchedOnce(MonitorFlappingStartedEvent::class);
    Event::assertDispatched(MonitorFlappingStartedEvent::class, fn (MonitorFlappingStartedEvent $event): bool => $event->monitorId === $this->monitor->id
        && $event->flappingSince->toTimeString() === '12:02:00'
        && $event->changes === 5
        && $event->windowMinutes === 30);
    $downFlags = [];
    Event::assertDispatched(MonitorWentDownEvent::class, function (MonitorWentDownEvent $event) use (&$downFlags): bool {
        $downFlags[] = $event->flapping;

        return true;
    });
    $upFlags = [];
    Event::assertDispatched(MonitorRecoveredEvent::class, function (MonitorRecoveredEvent $event) use (&$upFlags): bool {
        $upFlags[] = $event->flapping;

        return true;
    });
    expect($downFlags)->toBe([false, false, true, true])
        ->and($upFlags)->toBe([false, false, true]);
});

it('ends flapping once 30 minutes pass without a change, with the current state', function (): void {
    foreach ([['12:00:00', 500], ['12:00:30', 200], ['12:01:00', 500], ['12:01:30', 200], ['12:02:00', 500], ['12:02:30', 200]] as [$time, $status]) {
        checkFlappingAt($this, $time, $status);
    }

    expect(checkFlappingAt($this, '12:32:29', 200)->flapping_since)->not->toBeNull();
    Event::assertNotDispatched(MonitorFlappingEndedEvent::class);

    $monitor = checkFlappingAt($this, '12:32:30', 200);
    checkFlappingAt($this, '12:33:00', 200);

    expect($monitor->flapping_since)->toBeNull()
        ->and($monitor->flappingState()->changeCount())->toBe(5);
    Event::assertDispatchedOnce(MonitorFlappingEndedEvent::class);
    Event::assertDispatched(MonitorFlappingEndedEvent::class, fn (MonitorFlappingEndedEvent $event): bool => $event->flappingSince->toTimeString() === '12:02:00'
        && $event->endedAt->toTimeString() === '12:32:30'
        && $event->state === MonitorState::Up
        && $event->stableMinutes === 30);
});

it('neither ends nor counts flapping during a maintenance window', function (): void {
    foreach ([['12:00:00', 500], ['12:00:30', 200], ['12:01:00', 500], ['12:01:30', 200], ['12:02:00', 500]] as [$time, $status]) {
        checkFlappingAt($this, $time, $status);
    }
    $window = MaintenanceWindow::factory()->between('2027-03-10 12:30:00', '2027-03-10 13:00:00')->create(['organization_id' => $this->monitor->organization_id]);
    MaintenanceWindowMonitor::query()->create(['maintenance_window_id' => $window->id, 'monitor_id' => $this->monitor->id]);

    $inWindow = checkFlappingAt($this, '12:40:00', 200);
    $after = checkFlappingAt($this, '13:00:00', 500);

    expect($inWindow->flapping_since?->toTimeString())->toBe('12:02:00')
        ->and($inWindow->state)->toBe(MonitorState::Down)
        ->and($inWindow->flappingState()->changeCount())->toBe(5)
        ->and($after->flapping_since)->toBeNull();
    Event::assertDispatchedOnce(MonitorFlappingEndedEvent::class);
});
