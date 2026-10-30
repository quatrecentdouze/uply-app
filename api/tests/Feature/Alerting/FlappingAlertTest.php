<?php

declare(strict_types=1);

use App\Alerting\Enums\DeliveryKind;
use App\Alerting\Jobs\SendAlertJob;
use App\Alerting\Models\AlertChannel;
use App\Alerting\Models\AlertDelivery;
use App\Incidents\Events\IncidentOpenedEvent;
use App\Incidents\Events\IncidentResolvedEvent;
use App\Incidents\Models\Incident;
use App\Monitoring\Enums\MonitorState;
use App\Monitoring\Events\MonitorFlappingEndedEvent;
use App\Monitoring\Events\MonitorFlappingStartedEvent;
use App\Monitoring\Models\Monitor;
use App\Monitoring\Probe\Probe;
use App\Monitoring\Storage\CheckStorageMaintenance;
use App\Monitoring\Values\ProbeRequest;
use App\Monitoring\Values\ProbeResult;
use App\Monitoring\Values\ProbeTimings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->clock = freezeClock('2027-03-10 12:00:00');
    Queue::fake([SendAlertJob::class]);
    $this->monitor = Monitor::factory()->create(['name' => 'Shop', 'url' => 'https://shop.example.com/']);
    $this->email = AlertChannel::factory()->create(['organization_id' => $this->monitor->organization_id]);
    $this->discord = AlertChannel::factory()->discord()->create(['organization_id' => $this->monitor->organization_id]);
    foreach ([$this->email, $this->discord] as $channel) {
        DB::table('alert_channel_monitor')->insert(['alert_channel_id' => $channel->id, 'monitor_id' => $this->monitor->id]);
    }
    $this->incident = Incident::factory()->create(['monitor_id' => $this->monitor->id, 'started_at' => '2027-03-10 11:59:00+00']);
});

function incidentAlertsOf(object $test, bool $monitorFlapping): array
{
    $startedAt = CarbonImmutable::parse('2027-03-10 11:59:00', 'UTC');
    event(new IncidentOpenedEvent($test->incident->id, $test->monitor->organization_id, $test->monitor->id, $startedAt, 'http_status', 500, $monitorFlapping));
    event(new IncidentResolvedEvent($test->incident->id, $test->monitor->organization_id, $test->monitor->id, $startedAt, $startedAt->addMinute(), 60, $monitorFlapping));

    return AlertDelivery::query()->orderBy('kind')->pluck('kind')->map(fn (mixed $kind): mixed => $kind instanceof BackedEnum ? $kind->value : $kind)->all();
}

it('sends no down nor up alert for an incident of a flapping monitor', function (): void {
    expect(incidentAlertsOf($this, monitorFlapping: true))->toBe([]);
    Queue::assertNothingPushed();
});

it('still sends the down and up alerts of a monitor that is not flapping', function (): void {
    expect(incidentAlertsOf($this, monitorFlapping: false))->toBe(['down', 'down', 'up', 'up']);
});

it('sends one flapping alert per enabled channel when the flapping starts, once per episode', function (): void {
    $since = CarbonImmutable::parse('2027-03-10 12:04:00', 'UTC');

    event(new MonitorFlappingStartedEvent($this->monitor->id, $this->monitor->organization_id, $since, 5, 30));
    event(new MonitorFlappingStartedEvent($this->monitor->id, $this->monitor->organization_id, $since, 5, 30));

    $deliveries = AlertDelivery::query()->get();
    expect($deliveries)->toHaveCount(2)
        ->and($deliveries->pluck('kind')->map(fn (DeliveryKind $kind): string => $kind->value)->unique()->all())->toBe(['flapping_started'])
        ->and($deliveries->pluck('incident_id')->unique()->all())->toBe([null])
        ->and($deliveries->every(fn (AlertDelivery $delivery): bool => $delivery->monitor_id === $this->monitor->id && $delivery->flapping_since?->equalTo($since) === true))->toBeTrue();
    Queue::assertPushed(SendAlertJob::class, 2);
    Queue::assertPushed(SendAlertJob::class, fn (SendAlertJob $job): bool => $job->message->title === 'Shop is flapping'
        && $job->message->summary === 'Changed state 5 times in 30 minutes'
        && $job->message->color === '#f36e29'
        && $job->message->link === "http://localhost:5173/monitors/{$this->monitor->id}"
        && $job->message->fields[0]->text === 'https://shop.example.com/');
});

it('sends one stable again alert with the state the flapping ends on', function (string $state, string $label, string $color): void {
    $since = CarbonImmutable::parse('2027-03-10 12:04:00', 'UTC');

    event(new MonitorFlappingEndedEvent($this->monitor->id, $this->monitor->organization_id, $since, $since->addMinutes(31), MonitorState::from($state), 30));
    event(new MonitorFlappingEndedEvent($this->monitor->id, $this->monitor->organization_id, $since, $since->addMinutes(32), MonitorState::from($state), 30));

    expect(AlertDelivery::query()->where('kind', 'flapping_ended')->count())->toBe(2);
    Queue::assertPushed(SendAlertJob::class, 2);
    Queue::assertPushed(SendAlertJob::class, fn (SendAlertJob $job): bool => $job->message->title === 'Shop is stable again'
        && $job->message->summary === 'No state change for 30 minutes'
        && $job->message->color === $color
        && $job->message->fields[0]->label === 'State'
        && $job->message->fields[0]->text === $label);
})->with([
    ['up', 'Up', '#34b591'],
    ['down', 'Down', '#f23b39'],
]);

it('gives a monitor that flaps one alert at the start and one once stable, and nothing in between', function (): void {
    $probe = new class implements Probe
    {
        public int $status = 200;

        public function run(ProbeRequest $request): ProbeResult
        {
            return ProbeResult::responded($this->status, new ProbeTimings(totalMs: 40));
        }
    };
    app()->instance(Probe::class, $probe);
    app(CheckStorageMaintenance::class)->run($this->clock->now());
    $this->incident->delete();
    Monitor::query()->whereKey($this->monitor->id)->update(['interval_seconds' => 30, 'failure_threshold' => 1, 'recovery_threshold' => 1, 'state' => MonitorState::Up]);
    $kindsAt = function (string $time, int $status) use ($probe): array {
        $probe->status = $status;
        $this->clock->moveTo(CarbonImmutable::parse("2027-03-10 {$time}", 'UTC'));
        Monitor::query()->whereKey($this->monitor->id)->update(['next_check_at' => $this->clock->now()]);
        $this->artisan('checks:dispatch')->assertSuccessful();

        return AlertDelivery::query()->where('alert_channel_id', $this->email->id)->orderBy('created_at')->orderBy('kind')->pluck('kind')->map(fn (DeliveryKind $kind): string => $kind->value)->all();
    };

    $kindsAt('12:00:00', 500);
    $kindsAt('12:00:30', 200);
    $kindsAt('12:01:00', 500);
    $kindsAt('12:01:30', 200);
    expect($kindsAt('12:02:00', 500))->toBe(['down', 'up', 'down', 'up', 'flapping_started']);
    foreach (['12:02:30', '12:03:00', '12:03:30', '12:04:00'] as $index => $time) {
        $kindsAt($time, $index % 2 === 0 ? 200 : 500);
    }
    $kindsAt('12:04:30', 200);
    expect($kindsAt('12:34:29', 200))->toBe(['down', 'up', 'down', 'up', 'flapping_started'])
        ->and($kindsAt('12:34:30', 200))->toBe(['down', 'up', 'down', 'up', 'flapping_started', 'flapping_ended'])
        ->and($kindsAt('12:35:00', 200))->toHaveCount(6)
        ->and(Incident::query()->whereNull('resolved_at')->count())->toBe(0);
});
