<?php

declare(strict_types=1);

use App\Alerting\Enums\DeliveryKind;
use App\Alerting\Enums\DeliveryStatus;
use App\Alerting\Enums\DisabledReason;
use App\Alerting\Events\AlertDeliveredEvent;
use App\Alerting\Jobs\SendAlertJob;
use App\Alerting\Mail\AlertMail;
use App\Alerting\Messages\AlertMessageBuilder;
use App\Alerting\Models\AlertChannel;
use App\Alerting\Models\AlertDelivery;
use App\Alerting\Settings\AlertingSettings;
use App\Alerting\Storage\DeliveryStore;
use App\Alerting\Values\QueuedDelivery;
use App\Incidents\Events\IncidentOpenedEvent;
use App\Incidents\Models\Incident;
use App\Incidents\Models\IncidentEvent;
use App\Monitoring\Contracts\MonitorDirectory;
use App\Monitoring\Models\Monitor;
use App\Monitoring\Probe\HostResolver;
use App\Monitoring\Storage\CheckStorageMaintenance;
use Illuminate\Http\Client\Request;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\StaticHostResolver;

beforeEach(function (): void {
    $this->clock = freezeClock('2027-03-10 12:00:00');
    app(CheckStorageMaintenance::class)->run($this->clock->now());
    app()->instance(HostResolver::class, new StaticHostResolver);
    Mail::fake();
    $this->monitor = Monitor::factory()->create(['name' => 'Billing API', 'url' => 'https://api.example.com/health', 'next_check_at' => $this->clock->now()]);
    $organizationId = $this->monitor->organization_id;
    $this->email = AlertChannel::factory()->create(['organization_id' => $organizationId, 'name' => 'Ops mail', 'settings' => [AlertChannel::RECIPIENTS_SETTING => ['ops@example.com']]]);
    $this->discord = AlertChannel::factory()->discord()->create(['organization_id' => $organizationId, 'name' => 'Ops Discord']);
    $this->slack = AlertChannel::factory()->slack()->create(['organization_id' => $organizationId, 'name' => 'Ops Slack']);
    $this->disabled = AlertChannel::factory()->discord('https://discord.com/api/webhooks/999/disabled-token')->disabled()->create(['organization_id' => $organizationId]);
    AlertChannel::factory()->slack('https://hooks.slack.com/services/T9/B9/unlinked-token')->create(['organization_id' => $organizationId]);
    foreach ([$this->email, $this->discord, $this->slack, $this->disabled] as $channel) {
        DB::table('alert_channel_monitor')->insert(['alert_channel_id' => $channel->id, 'monitor_id' => $this->monitor->id]);
    }
});

function openIncidentFor(Monitor $monitor): IncidentOpenedEvent
{
    $incident = Incident::factory()->create(['monitor_id' => $monitor->id, 'started_at' => '2027-03-10 11:58:00+00']);

    return new IncidentOpenedEvent($incident->id, $monitor->organization_id, $monitor->id, $incident->startedAt(), 'http_status', 500, false);
}

function queuedDeliveryFor(Monitor $monitor, AlertChannel $only): QueuedDelivery
{
    DB::table('alert_channel_monitor')->where('monitor_id', $monitor->id)->where('alert_channel_id', '!=', $only->id)->delete();
    $event = openIncidentFor($monitor);
    $queued = app(DeliveryStore::class)->createForIncident($event->incidentId, $monitor->id, DeliveryKind::Down);

    return $queued[0];
}

function alertJobFor(QueuedDelivery $delivery, Monitor $monitor): SendAlertJob
{
    $view = app(MonitorDirectory::class)->find($monitor->id);
    $message = app(AlertMessageBuilder::class)->down($view ?? throw new LogicException('monitor missing'), '01incident', $monitor->created_at ?? throw new LogicException('no date'), 'http_status', 500);

    return (new SendAlertJob($delivery->deliveryId, $delivery->channelId, $delivery->usesWebhook, $message, app(AlertingSettings::class)->delivery->retryPolicy()))
        ->withFakeQueueInteractions();
}

function runAlertJob(SendAlertJob $job): SendAlertJob
{
    app()->call([$job, 'handle']);

    return $job;
}

it('sends one down alert per linked channel, then one up alert at the recovery', function (): void {
    Http::fake([
        'api.example.com/*' => Http::sequence()->push('ok', 200)->push('ok', 200)->push('down', 500)->push('down', 500)->push('ok', 200)->push('ok', 200),
        'discord.com/*' => Http::response(null, 204),
        'hooks.slack.com/*' => Http::response('ok', 200),
    ]);

    foreach (['12:00:00', '12:00:20', '12:01:20', '12:01:40', '12:02:40', '12:03:00'] as $time) {
        $this->clock->moveTo($this->clock->now()->setTimeFromTimeString($time));
        $this->artisan('checks:dispatch')->assertSuccessful();
    }

    $deliveries = AlertDelivery::query()->orderBy('created_at')->orderBy('kind')->get();
    expect($deliveries->map(fn (AlertDelivery $delivery): string => "{$delivery->alert_channel_id}:{$delivery->kind->value}:{$delivery->status->value}:{$delivery->attempts}")->sort()->values()->all())
        ->toBe(collect([$this->email, $this->discord, $this->slack])
            ->flatMap(fn (AlertChannel $channel): array => ["{$channel->id}:down:sent:1", "{$channel->id}:up:sent:1"])
            ->sort()->values()->all());
    Mail::assertSent(AlertMail::class, fn (AlertMail $mail): bool => $mail->hasTo('ops@example.com') && $mail->alert->title === 'Billing API is down');
    Mail::assertSent(AlertMail::class, fn (AlertMail $mail): bool => $mail->alert->title === 'Billing API is back up' && $mail->alert->summary === 'Down for 1 min 20 s');
    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://discord.com/api/webhooks/1234567890/') && $request['embeds'][0]['title'] === 'Billing API is down');
    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://hooks.slack.com/services/T000/') && $request['text'] === 'Billing API is back up');
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'disabled-token') || str_contains($request->url(), 'unlinked-token'));
    $timeline = IncidentEvent::query()->orderBy('occurred_at')->get()->map(fn (IncidentEvent $event): string => $event->kind->value)->all();
    expect(array_count_values($timeline))->toBe(['opened' => 1, 'alert_sent' => 6, 'resolved' => 1])
        ->and(IncidentEvent::query()->where('kind', 'alert_sent')->first()?->data)->toHaveKeys(['channel_id', 'channel_type', 'kind', 'attempts']);
});

it('never sends twice for a replayed incident event', function (): void {
    Http::fake(['discord.com/*' => Http::response(null, 204), 'hooks.slack.com/*' => Http::response('ok', 200)]);
    $event = openIncidentFor($this->monitor);

    event($event);
    event($event);

    expect(AlertDelivery::query()->count())->toBe(3);
    Http::assertSentCount(2);
    Mail::assertSentCount(1);
});

it('retries a server error and sends on the next try', function (): void {
    Http::fake(['discord.com/*' => Http::sequence()->push('down', 500)->push(null, 204)]);
    $queued = queuedDeliveryFor($this->monitor, $this->discord);

    runAlertJob(alertJobFor($queued, $this->monitor))->assertReleased(10);
    $retried = AlertDelivery::query()->findOrFail($queued->deliveryId);
    expect($retried->status)->toBe(DeliveryStatus::Pending)
        ->and($retried->attempts)->toBe(1)
        ->and($retried->last_error)->toBe('HTTP 500 down');

    runAlertJob(alertJobFor($queued, $this->monitor))->assertNotReleased();
    $sent = AlertDelivery::query()->findOrFail($queued->deliveryId);
    expect($sent->status)->toBe(DeliveryStatus::Sent)
        ->and($sent->attempts)->toBe(2)
        ->and($sent->last_error)->toBeNull()
        ->and($sent->sent_at)->not->toBeNull();
});

it('fails after five server errors and notes it in the timeline', function (): void {
    Event::fake([AlertDeliveredEvent::class]);
    Http::fake(['hooks.slack.com/*' => Http::response('upstream', 503)]);
    $queued = queuedDeliveryFor($this->monitor, $this->slack);

    foreach ([10, 30, 60, 120] as $delay) {
        runAlertJob(alertJobFor($queued, $this->monitor))->assertReleased($delay);
    }
    runAlertJob(alertJobFor($queued, $this->monitor))->assertNotReleased();

    $failed = AlertDelivery::query()->findOrFail($queued->deliveryId);
    expect($failed->status)->toBe(DeliveryStatus::Failed)
        ->and($failed->attempts)->toBe(5)
        ->and($this->slack->fresh()?->is_enabled)->toBeTrue();
    Http::assertSentCount(5);
    Event::assertDispatched(AlertDeliveredEvent::class, fn (AlertDeliveredEvent $event): bool => $event->deliveryId === $queued->deliveryId
        && ! $event->wasSent()
        && $event->attempts === 5
        && $event->channelType->value === 'slack');
});

it('records a failed alert in the incident timeline', function (): void {
    Http::fake(['discord.com/*' => Http::response(['message' => 'Unknown Webhook', 'code' => 10015], 404)]);
    $queued = queuedDeliveryFor($this->monitor, $this->discord);

    runAlertJob(alertJobFor($queued, $this->monitor));

    $entry = IncidentEvent::query()->where('kind', 'alert_failed')->sole();
    expect($entry->data)->toEqual(['channel_id' => $this->discord->id, 'channel_type' => 'discord', 'kind' => 'down', 'attempts' => 1]);
});

it('disables a rejected webhook and sends it nothing afterwards', function (): void {
    Http::fake(['discord.com/*' => Http::response(['message' => 'Unknown Webhook', 'code' => 10015], 404), 'hooks.slack.com/*' => Http::response('ok', 200)]);

    event(openIncidentFor($this->monitor));
    $channel = $this->discord->fresh();
    expect($channel?->is_enabled)->toBeFalse()
        ->and($channel?->disabled_reason)->toBe(DisabledReason::WebhookRejected)
        ->and(AlertDelivery::query()->where('alert_channel_id', $this->discord->id)->sole()->last_error)->toBe('HTTP 404 Unknown Webhook');

    Incident::query()->update(['resolved_at' => '2027-03-10 11:59:00+00']);
    $this->clock->advance(5);
    event(openIncidentFor($this->monitor));

    expect(AlertDelivery::query()->where('alert_channel_id', $this->discord->id)->count())->toBe(1);
    Http::assertSentCount(3);
});

it('fails a pending delivery whose channel got disabled without sending it', function (): void {
    Http::fake();
    $queued = queuedDeliveryFor($this->monitor, $this->discord);
    $this->discord->update(['is_enabled' => false]);

    runAlertJob(alertJobFor($queued, $this->monitor));

    $delivery = AlertDelivery::query()->findOrFail($queued->deliveryId);
    expect($delivery->status)->toBe(DeliveryStatus::Failed)
        ->and($delivery->attempts)->toBe(0)
        ->and($delivery->last_error)->toBe('channel disabled');
    Http::assertNothingSent();
});

it('holds a second message to the same webhook within a second without burning a try', function (): void {
    Http::fake(['discord.com/*' => Http::response(null, 204)]);
    $first = queuedDeliveryFor($this->monitor, $this->discord);
    $second = app(DeliveryStore::class)->createTest($this->discord);
    $policy = app(AlertingSettings::class)->delivery->retryPolicy();
    $message = app(AlertMessageBuilder::class)->test('Ops Discord', $this->clock->now());

    dispatch(new SendAlertJob($first->deliveryId, $first->channelId, true, $message, $policy));
    dispatch(new SendAlertJob($second->deliveryId, $second->channelId, true, $message, $policy));

    expect(AlertDelivery::query()->findOrFail($first->deliveryId)->status)->toBe(DeliveryStatus::Sent)
        ->and(AlertDelivery::query()->findOrFail($second->deliveryId)->status)->toBe(DeliveryStatus::Pending)
        ->and(AlertDelivery::query()->findOrFail($second->deliveryId)->attempts)->toBe(0)
        ->and((new SendAlertJob($first->deliveryId, $first->channelId, false, $message, $policy))->middleware())->toBe([])
        ->and((new SendAlertJob($first->deliveryId, $first->channelId, true, $message, $policy))->middleware()[0])->toBeInstanceOf(RateLimited::class);
    Http::assertSentCount(1);
});

it('sends a test alert even to a disabled channel, outside any timeline, five times a minute', function (): void {
    Http::fake(['discord.com/*' => Http::response(null, 204)]);

    $this->artisan('alerting:test', ['channel' => $this->disabled->id])->expectsOutputToContain('status=queued delivery=')->assertSuccessful();

    $test = AlertDelivery::query()->sole();
    expect($test->kind)->toBe(DeliveryKind::Test)
        ->and($test->status)->toBe(DeliveryStatus::Sent)
        ->and($test->incident_id)->toBeNull()
        ->and($this->disabled->fresh()?->is_enabled)->toBeFalse()
        ->and(IncidentEvent::query()->count())->toBe(0);
    Http::assertSent(fn (Request $request): bool => $request['embeds'][0]['title'] === 'Test alert from Uply' && ! isset($request['embeds'][0]['url']));

    foreach (range(2, 5) as $send) {
        $this->clock->advance(1);
        $this->artisan('alerting:test', ['channel' => $this->disabled->id])->assertSuccessful();
    }
    $this->artisan('alerting:test', ['channel' => $this->disabled->id])->expectsOutputToContain('status=limited retry_in_seconds=')->assertFailed();
    $this->artisan('alerting:test', ['channel' => '01unknownchannel000000000'])->expectsOutput('status=channel_not_found')->assertFailed();
});
