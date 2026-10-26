<?php

declare(strict_types=1);

use App\Alerting\Jobs\SendAlertJob;
use App\Alerting\Models\AlertChannel;
use App\Alerting\Models\AlertDelivery;
use App\Incidents\Models\Incident;
use App\Maintenance\Models\MaintenanceWindow;
use App\Maintenance\Models\MaintenanceWindowMonitor;
use App\Monitoring\Enums\MonitorState;
use App\Monitoring\Events\CertificateExpiringEvent;
use App\Monitoring\Events\CheckCompletedEvent;
use App\Monitoring\Models\Monitor;
use App\Monitoring\Probe\Probe;
use App\Monitoring\Storage\CheckStorageMaintenance;
use App\Monitoring\Values\ProbeRequest;
use App\Monitoring\Values\ProbeResult;
use App\Monitoring\Values\ProbeTimings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->clock = freezeClock('2027-03-10 12:00:00');
    app(CheckStorageMaintenance::class)->run($this->clock->now());
    $this->probe = new class implements Probe
    {
        public int $status = 500;

        public ?CarbonImmutable $certificateEnd = null;

        public function run(ProbeRequest $request): ProbeResult
        {
            return ProbeResult::responded($this->status, new ProbeTimings(totalMs: 40))->withTlsExpiresAt($this->certificateEnd);
        }
    };
    app()->instance(Probe::class, $this->probe);
    Queue::fake([SendAlertJob::class]);
    Event::fake([CheckCompletedEvent::class, CertificateExpiringEvent::class]);
    $this->monitor = Monitor::factory()->create([
        'url' => 'https://shop.example.com/',
        'interval_seconds' => 60,
        'next_check_at' => $this->clock->now(),
    ]);
    Monitor::query()->whereKey($this->monitor->id)->update(['state' => MonitorState::Up, 'state_changed_at' => '2027-03-10 11:00:00+00']);
    $channel = AlertChannel::factory()->create(['organization_id' => $this->monitor->organization_id]);
    DB::table('alert_channel_monitor')->insert(['alert_channel_id' => $channel->id, 'monitor_id' => $this->monitor->id]);
    $window = MaintenanceWindow::factory()->between('2027-03-10 12:00:00', '2027-03-10 12:03:00')->create(['organization_id' => $this->monitor->organization_id]);
    MaintenanceWindowMonitor::query()->create(['maintenance_window_id' => $window->id, 'monitor_id' => $this->monitor->id]);
});

function checkDuringMaintenanceAt(object $test, string $time): Monitor
{
    $test->clock->moveTo($test->clock->now()->setTimeFromTimeString($test->monitor->fresh()?->nextCheckAt()->toTimeString() ?? $time));
    expect($test->clock->now()->toTimeString())->toBe($time);
    test()->artisan('checks:dispatch')->assertSuccessful();

    return $test->monitor->fresh() ?? throw new LogicException('the monitor is gone');
}

it('keeps checking a failing service in a window without incident nor alert, then opens the incident once the window ends', function (): void {
    $timeline = [];
    foreach (['12:00:00', '12:01:00', '12:02:00', '12:03:00', '12:03:20'] as $time) {
        $monitor = checkDuringMaintenanceAt($this, $time);
        $timeline[] = [$time, $monitor->state->value, $monitor->consecutive_failures, $monitor->nextCheckAt()->toTimeString(), Incident::query()->count(), AlertDelivery::query()->count()];
    }

    expect($timeline)->toBe([
        ['12:00:00', 'up', 0, '12:01:00', 0, 0],
        ['12:01:00', 'up', 0, '12:02:00', 0, 0],
        ['12:02:00', 'up', 0, '12:03:00', 0, 0],
        ['12:03:00', 'up', 1, '12:03:20', 0, 0],
        ['12:03:20', 'down', 2, '12:04:20', 1, 1],
    ]);
    expect(Incident::query()->sole()->startedAt()->toTimeString())->toBe('12:03:00')
        ->and(AlertDelivery::query()->sole()->kind->value)->toBe('down')
        ->and(DB::table('check_results')->orderBy('checked_at')->pluck('in_maintenance')->all())->toBe([true, true, true, false, false]);
    $inMaintenance = [];
    Event::assertDispatched(CheckCompletedEvent::class, function (CheckCompletedEvent $event) use (&$inMaintenance): bool {
        $inMaintenance[] = $event->inMaintenance;

        return true;
    });
    expect($inMaintenance)->toBe([true, true, true, false, false]);
});

it('counts the results of a window apart from the hourly totals and latency', function (): void {
    foreach (['12:00:00', '12:01:00', '12:02:00', '12:03:00'] as $time) {
        checkDuringMaintenanceAt($this, $time);
    }

    $hour = DB::table('monitor_hourly_stats')->sole();
    expect($hour->checks_total)->toBe(1)
        ->and($hour->checks_up)->toBe(0)
        ->and($hour->checks_maintenance)->toBe(3)
        ->and($hour->response_ms_count)->toBe(1);
});

it('leaves an incident open before the window open while the service is back, and resolves it after the window', function (): void {
    Monitor::query()->whereKey($this->monitor->id)->update(['state' => MonitorState::Down]);
    Incident::factory()->create(['organization_id' => $this->monitor->organization_id, 'monitor_id' => $this->monitor->id, 'started_at' => '2027-03-10 11:50:00+00']);
    $this->probe->status = 200;

    foreach (['12:00:00', '12:01:00', '12:02:00'] as $time) {
        expect(checkDuringMaintenanceAt($this, $time)->state)->toBe(MonitorState::Down);
    }
    expect(Incident::query()->sole()->resolved_at)->toBeNull();

    checkDuringMaintenanceAt($this, '12:03:00');
    checkDuringMaintenanceAt($this, '12:03:20');

    expect(Incident::query()->sole()->resolved_at?->toTimeString())->toBe('12:03:00');
});

it('leaves the certificate alone in a window', function (): void {
    Monitor::query()->whereKey($this->monitor->id)->update(['tls_expires_at' => '2027-04-30 00:00:00+00']);
    $this->probe->status = 200;
    $this->probe->certificateEnd = CarbonImmutable::parse('2027-03-12 00:00:00', 'UTC');

    checkDuringMaintenanceAt($this, '12:00:00');

    expect($this->monitor->fresh()?->tls_expires_at?->toDateString())->toBe('2027-04-30')
        ->and($this->monitor->fresh()?->tls_alerted_days)->toBeNull();
    Event::assertNotDispatched(CertificateExpiringEvent::class);
});
