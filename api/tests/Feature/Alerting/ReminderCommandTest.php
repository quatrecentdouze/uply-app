<?php

declare(strict_types=1);

use App\Alerting\Jobs\AlertQueue;
use App\Alerting\Jobs\SendAlertJob;
use App\Alerting\Messages\AlertMessageBuilder;
use App\Alerting\Models\AlertChannel;
use App\Alerting\Models\AlertDelivery;
use App\Alerting\Settings\AlertingSettings;
use App\Alerting\Values\AlertField;
use App\Incidents\Models\Incident;
use App\Maintenance\Models\MaintenanceWindow;
use App\Maintenance\Models\MaintenanceWindowMonitor;
use App\Monitoring\Contracts\MonitorDirectory;
use App\Monitoring\Enums\MonitorState;
use App\Monitoring\Models\Monitor;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->clock = freezeClock('2027-03-10 12:00:00');
    Queue::fake([SendAlertJob::class]);
    $this->monitor = Monitor::factory()->create(['name' => 'Billing API', 'url' => 'https://billing.example.com/health']);
    Monitor::query()->whereKey($this->monitor->id)->update(['state' => MonitorState::Down]);
    $this->incident = Incident::factory()->create([
        'monitor_id' => $this->monitor->id,
        'started_at' => '2027-03-10 11:00:00+00',
        'cause_failure_kind' => 'http_status',
        'cause_http_status' => 503,
    ]);
    $this->channel = remindingChannel($this->monitor, 15);
    alertDeliveredAt($this->channel, $this->incident, 'down', '2027-03-10 11:01:00');
});

function remindingChannel(Monitor $monitor, ?int $minutes, bool $enabled = true): AlertChannel
{
    $channel = AlertChannel::factory()->create(['organization_id' => $monitor->organization_id, 'reminder_interval_minutes' => $minutes, 'is_enabled' => $enabled]);
    DB::table('alert_channel_monitor')->insert(['alert_channel_id' => $channel->id, 'monitor_id' => $monitor->id]);

    return $channel;
}

function alertDeliveredAt(AlertChannel $channel, Incident $incident, string $kind, string $createdAt, ?int $number = null): void
{
    AlertDelivery::query()->insert([
        'id' => (new AlertDelivery)->newUniqueId(),
        'alert_channel_id' => $channel->id,
        'incident_id' => $incident->id,
        'kind' => $kind,
        'reminder_number' => $number,
        'status' => 'sent',
        'attempts' => 1,
        'created_at' => $createdAt.'+00',
        'updated_at' => $createdAt.'+00',
    ]);
}

function remindersOf(AlertChannel $channel): array
{
    return AlertDelivery::query()->where('alert_channel_id', $channel->id)->where('kind', 'reminder')->orderBy('reminder_number')->pluck('reminder_number')->all();
}

it('queues the next reminder once the interval has passed since the latest alert of the channel', function (): void {
    $this->artisan('alerts:remind')->expectsOutput('queued=1')->assertSuccessful();

    expect(remindersOf($this->channel))->toBe([1]);
    Queue::assertPushedOn('alerts', SendAlertJob::class, fn (SendAlertJob $job): bool => $job->channelId === $this->channel->id
        && $job->message->title === 'Billing API is still down'
        && $job->message->summary === 'https://billing.example.com/health'
        && $job->message->link === "http://localhost:5173/incidents/{$this->incident->id}"
        && array_map(fn (AlertField $field): string => $field->text, $job->message->fields) === ['HTTP 503', '1 h']);
});

it('waits for the interval of each channel', function (): void {
    $hourly = remindingChannel($this->monitor, 60);
    alertDeliveredAt($hourly, $this->incident, 'down', '2027-03-10 11:01:00');
    alertDeliveredAt($this->channel, $this->incident, 'reminder', '2027-03-10 11:50:00', 1);

    $this->artisan('alerts:remind')->expectsOutput('queued=0')->assertSuccessful();
    $this->clock->moveTo(CarbonImmutable::parse('2027-03-10 12:05:00', 'UTC'));
    $this->artisan('alerts:remind')->expectsOutput('queued=2')->assertSuccessful();

    expect(remindersOf($this->channel))->toBe([1, 2])
        ->and(remindersOf($hourly))->toBe([1]);
});

it('counts from the opening of the incident for a channel that got no alert yet', function (): void {
    $late = remindingChannel($this->monitor, 120);
    $this->clock->moveTo(CarbonImmutable::parse('2027-03-10 12:59:59', 'UTC'));
    $this->artisan('alerts:remind')->assertSuccessful();
    expect(remindersOf($late))->toBe([]);

    $this->clock->moveTo(CarbonImmutable::parse('2027-03-10 13:00:00', 'UTC'));
    $this->artisan('alerts:remind')->assertSuccessful();
    expect(remindersOf($late))->toBe([1]);
});

it('stops at the most reminders of an incident', function (): void {
    config(['alerting.reminders.max_per_incident' => 2]);
    app()->forgetInstance(AlertingSettings::class);
    alertDeliveredAt($this->channel, $this->incident, 'reminder', '2027-03-10 11:16:00', 1);
    alertDeliveredAt($this->channel, $this->incident, 'reminder', '2027-03-10 11:31:00', 2);

    $this->artisan('alerts:remind')->expectsOutput('queued=0')->assertSuccessful();

    expect(remindersOf($this->channel))->toBe([1, 2]);
});

it('reminds nobody for a resolved incident, a disabled channel or a channel without reminders', function (): void {
    remindingChannel($this->monitor, 15, enabled: false);
    remindingChannel($this->monitor, null);
    $this->incident->update(['resolved_at' => '2027-03-10 11:59:00+00']);

    $this->artisan('alerts:remind')->expectsOutput('queued=0')->assertSuccessful();

    expect(AlertDelivery::query()->where('kind', 'reminder')->count())->toBe(0);
});

it('stays silent while the monitor is in a maintenance window or paused, then sends one reminder only', function (): void {
    $window = MaintenanceWindow::factory()->between('2027-03-10 11:30:00', '2027-03-10 14:00:00')->create(['organization_id' => $this->monitor->organization_id]);
    MaintenanceWindowMonitor::query()->create(['maintenance_window_id' => $window->id, 'monitor_id' => $this->monitor->id]);

    $this->artisan('alerts:remind')->expectsOutput('queued=0')->assertSuccessful();
    $this->clock->moveTo(CarbonImmutable::parse('2027-03-10 13:59:00', 'UTC'));
    $this->artisan('alerts:remind')->expectsOutput('queued=0')->assertSuccessful();

    Monitor::query()->whereKey($this->monitor->id)->update(['is_paused' => true]);
    $this->clock->moveTo(CarbonImmutable::parse('2027-03-10 14:00:00', 'UTC'));
    $this->artisan('alerts:remind')->expectsOutput('queued=0')->assertSuccessful();

    Monitor::query()->whereKey($this->monitor->id)->update(['is_paused' => false]);
    $this->artisan('alerts:remind')->expectsOutput('queued=1')->assertSuccessful();
    expect(remindersOf($this->channel))->toBe([1]);
});

it('never sends the same reminder twice when two runs overlap', function (): void {
    $this->artisan('alerts:remind')->expectsOutput('queued=1')->assertSuccessful();
    $this->artisan('alerts:remind')->expectsOutput('queued=0')->assertSuccessful();

    $view = app(MonitorDirectory::class)->find($this->monitor->id) ?? throw new LogicException('the monitor is gone');
    $message = app(AlertMessageBuilder::class)->reminder($view, $this->incident->id, $this->incident->startedAt(), 'http_status', 503, $this->clock->now());
    expect(app(AlertQueue::class)->queueReminder($this->channel, $this->incident->id, 1, $message))->toBeFalse()
        ->and(remindersOf($this->channel))->toBe([1]);
    Queue::assertPushed(SendAlertJob::class, 1);
});

it('runs every minute on one server without overlapping', function (): void {
    $reminders = array_values(array_filter(
        app(Schedule::class)->events(),
        fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'alerts:remind'),
    ));

    expect($reminders)->toHaveCount(1)
        ->and($reminders[0]->expression)->toBe('* * * * *')
        ->and($reminders[0]->withoutOverlapping)->toBeTrue()
        ->and($reminders[0]->onOneServer)->toBeTrue();
});
