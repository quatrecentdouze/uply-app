<?php

declare(strict_types=1);

use App\Alerting\Jobs\SendAlertJob;
use App\Alerting\Models\AlertChannel;
use App\Alerting\Models\AlertDelivery;
use App\Incidents\Events\IncidentOpenedEvent;
use App\Incidents\Events\IncidentResolvedEvent;
use App\Incidents\Models\Incident;
use App\Monitoring\Models\Monitor;
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
