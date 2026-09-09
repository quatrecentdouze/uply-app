<?php

declare(strict_types=1);

use App\Incidents\Events\IncidentOpenedEvent;
use App\Incidents\Events\IncidentResolvedEvent;
use App\Monitoring\Enums\CheckOutcome;
use App\Monitoring\Enums\MonitorState;
use App\Monitoring\Events\CheckCompletedEvent;
use App\Monitoring\Jobs\RunCheckJob;
use App\Monitoring\Models\Monitor;
use App\Monitoring\Probe\HostResolver;
use App\Monitoring\Storage\CheckStorageMaintenance;
use App\Realtime\Broadcasts\IncidentOpenedBroadcast;
use App\Realtime\Broadcasts\IncidentResolvedBroadcast;
use App\Realtime\Broadcasts\MonitorCheckedBroadcast;
use App\Realtime\Broadcasts\MonitorStateChangedBroadcast;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\StaticHostResolver;

function checkCompleted(MonitorState $state, MonitorState $previous): CheckCompletedEvent
{
    return new CheckCompletedEvent('01monitor', '01organization', CarbonImmutable::parse('2027-03-10 12:00:20', 'UTC'), CheckOutcome::Up, null, 200, 42, $state, $previous);
}

it('names each broadcast, its payload, its private organization channel and its queue', function (ShouldBroadcast $broadcast, string $name, array $payload): void {
    expect($broadcast->broadcastAs())->toBe($name)
        ->and($broadcast->broadcastWith())->toBe($payload)
        ->and(array_map(fn (mixed $channel): string => (string) $channel, $broadcast->broadcastOn()))->toBe(['private-organization.01organization'])
        ->and($broadcast->broadcastQueue())->toBe('realtime')
        ->and($broadcast)->toBeInstanceOf(ShouldDispatchAfterCommit::class);
})->with([
    'monitor checked' => [
        new MonitorCheckedBroadcast('01organization', '01monitor', '2027-03-10T12:00:20Z', 'up', 42, 'up'),
        'monitor.checked',
        ['monitor_id' => '01monitor', 'checked_at' => '2027-03-10T12:00:20Z', 'outcome' => 'up', 'response_ms' => 42, 'state' => 'up'],
    ],
    'monitor state changed' => [
        new MonitorStateChangedBroadcast('01organization', '01monitor', 'up', '2027-03-10T12:00:20Z'),
        'monitor.state-changed',
        ['monitor_id' => '01monitor', 'state' => 'up', 'changed_at' => '2027-03-10T12:00:20Z'],
    ],
    'incident opened' => [
        new IncidentOpenedBroadcast('01organization', '01incident', '01monitor', '2027-03-10T12:01:20Z'),
        'incident.opened',
        ['incident_id' => '01incident', 'monitor_id' => '01monitor', 'started_at' => '2027-03-10T12:01:20Z'],
    ],
    'incident resolved' => [
        new IncidentResolvedBroadcast('01organization', '01incident', '01monitor', '2027-03-10T12:02:40Z', 80),
        'incident.resolved',
        ['incident_id' => '01incident', 'monitor_id' => '01monitor', 'resolved_at' => '2027-03-10T12:02:40Z', 'duration_seconds' => 80],
    ],
]);

it('broadcasts every check and a state change only when the state moved, pending to up included', function (): void {
    Event::fake([MonitorCheckedBroadcast::class, MonitorStateChangedBroadcast::class]);

    event(checkCompleted(MonitorState::Up, MonitorState::Pending));
    event(checkCompleted(MonitorState::Up, MonitorState::Up));

    Event::assertDispatchedTimes(MonitorCheckedBroadcast::class, 2);
    Event::assertDispatchedOnce(MonitorStateChangedBroadcast::class);
    Event::assertDispatched(MonitorStateChangedBroadcast::class, fn (MonitorStateChangedBroadcast $broadcast): bool => $broadcast->broadcastWith() === [
        'monitor_id' => '01monitor',
        'state' => 'up',
        'changed_at' => '2027-03-10T12:00:20Z',
    ]);
});

it('broadcasts the opening and the resolution of an incident', function (): void {
    Event::fake([IncidentOpenedBroadcast::class, IncidentResolvedBroadcast::class]);

    event(new IncidentOpenedEvent('01incident', '01organization', '01monitor', CarbonImmutable::parse('2027-03-10 12:01:20', 'UTC'), 'http_status', 500));
    event(new IncidentResolvedEvent('01incident', '01organization', '01monitor', CarbonImmutable::parse('2027-03-10 12:01:20', 'UTC'), CarbonImmutable::parse('2027-03-10 12:02:40', 'UTC'), 80));

    Event::assertDispatched(IncidentOpenedBroadcast::class, fn (IncidentOpenedBroadcast $broadcast): bool => $broadcast->broadcastWith()['started_at'] === '2027-03-10T12:01:20Z');
    Event::assertDispatched(IncidentResolvedBroadcast::class, fn (IncidentResolvedBroadcast $broadcast): bool => $broadcast->broadcastWith()['duration_seconds'] === 80);
});

it('queues the broadcasts of a real check on the realtime queue', function (): void {
    $clock = freezeClock('2027-03-10 12:00:00');
    app(CheckStorageMaintenance::class)->run($clock->now());
    app()->instance(HostResolver::class, new StaticHostResolver);
    Http::fake(['api.example.com/*' => Http::response('ok', 200)]);
    $monitor = Monitor::factory()->create(['url' => 'https://api.example.com/health', 'next_check_at' => $clock->now()]);
    Queue::fake([BroadcastEvent::class]);

    dispatch_sync(new RunCheckJob($monitor->id, $clock->now()));

    Queue::assertPushedOn('realtime', BroadcastEvent::class, fn (BroadcastEvent $job): bool => $job->event instanceof MonitorCheckedBroadcast
        && $job->event->monitorId === $monitor->id
        && $job->event->organizationId === $monitor->organization_id
        && $job->event->state === 'pending');
    Queue::assertNotPushed(BroadcastEvent::class, fn (BroadcastEvent $job): bool => $job->event instanceof MonitorStateChangedBroadcast);
});
