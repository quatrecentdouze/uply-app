<?php

declare(strict_types=1);

use App\Maintenance\Contracts\MaintenanceSchedule;
use App\Maintenance\Contracts\ScheduledWindow;
use App\Maintenance\Models\MaintenanceWindow;
use App\Maintenance\Models\MaintenanceWindowMonitor;
use App\Monitoring\Models\Monitor;
use Carbon\CarbonImmutable;

function scheduledMoment(string $time): CarbonImmutable
{
    return CarbonImmutable::parse("2027-03-10 {$time}", 'UTC');
}

function windowOver(array $monitors, string $startsAt, string $endsAt, string $name = 'Upgrade'): MaintenanceWindow
{
    $window = MaintenanceWindow::factory()->between("2027-03-10 {$startsAt}", "2027-03-10 {$endsAt}")->create(['name' => $name]);
    foreach ($monitors as $monitor) {
        MaintenanceWindowMonitor::query()->create(['maintenance_window_id' => $window->id, 'monitor_id' => $monitor->id]);
    }

    return $window;
}

beforeEach(function (): void {
    $this->schedule = app(MaintenanceSchedule::class);
    $this->monitor = Monitor::factory()->create();
});

it('covers a monitor from the start of its window included to its end excluded', function (string $at, bool $covered): void {
    windowOver([$this->monitor], '12:00:00', '13:00:00');

    expect($this->schedule->covers($this->monitor->id, scheduledMoment($at)))->toBe($covered);
})->with([
    'before' => ['11:59:59', false],
    'at the start' => ['12:00:00', true],
    'inside' => ['12:30:00', true],
    'at the end' => ['13:00:00', false],
]);

it('covers only the monitors of the window', function (): void {
    $other = Monitor::factory()->create();
    windowOver([$other], '12:00:00', '13:00:00');

    expect($this->schedule->covers($this->monitor->id, scheduledMoment('12:30:00')))->toBeFalse()
        ->and($this->schedule->covers($other->id, scheduledMoment('12:30:00')))->toBeTrue();
});

it('gives the active window of each monitor, else its next one, else nothing', function (): void {
    $upcoming = Monitor::factory()->create();
    $free = Monitor::factory()->create();
    windowOver([$this->monitor], '09:00:00', '10:00:00', 'Over');
    $active = windowOver([$this->monitor], '12:00:00', '13:00:00', 'Now');
    windowOver([$this->monitor, $upcoming], '15:00:00', '16:00:00', 'Later');
    $next = windowOver([$upcoming], '14:00:00', '14:30:00', 'Sooner');

    $windows = $this->schedule->windowsFor([$this->monitor->id, $upcoming->id, $free->id, 42], scheduledMoment('12:15:00'));

    expect(array_keys($windows))->toEqualCanonicalizing([$this->monitor->id, $upcoming->id])
        ->and($windows[$this->monitor->id])->toEqual(new ScheduledWindow($active->id, 'Now', scheduledMoment('12:00:00'), scheduledMoment('13:00:00'), true))
        ->and($windows[$upcoming->id])->toEqual(new ScheduledWindow($next->id, 'Sooner', scheduledMoment('14:00:00'), scheduledMoment('14:30:00'), false))
        ->and($this->schedule->windowsFor([], scheduledMoment('12:15:00')))->toBe([]);
});
