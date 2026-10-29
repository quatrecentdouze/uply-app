<?php

declare(strict_types=1);

use App\Alerting\Models\AlertChannel;
use App\Alerting\Models\AlertDelivery;
use App\Incidents\Models\Incident;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function (): void {
    $this->incident = Incident::factory()->create();
    $this->channel = AlertChannel::factory()->create(['organization_id' => $this->incident->organization_id]);
});

function storedDelivery(object $test, array $attributes): AlertDelivery
{
    return AlertDelivery::query()->create([
        'alert_channel_id' => $test->channel->id,
        'incident_id' => $test->incident->id,
        'status' => 'pending',
        'attempts' => 0,
        ...$attributes,
    ]);
}

it('keeps one reminder per channel, incident and number', function (): void {
    storedDelivery($this, ['kind' => 'reminder', 'reminder_number' => 1]);
    storedDelivery($this, ['kind' => 'reminder', 'reminder_number' => 2]);

    expect(fn () => storedDelivery($this, ['kind' => 'reminder', 'reminder_number' => 2]))->toThrow(UniqueConstraintViolationException::class);
});

it('keeps one down and one up alert per channel and incident', function (): void {
    storedDelivery($this, ['kind' => 'down']);
    storedDelivery($this, ['kind' => 'up']);

    expect(fn () => storedDelivery($this, ['kind' => 'down']))->toThrow(UniqueConstraintViolationException::class);
});

it('refuses a reminder without its number or incident and a number on another kind', function (array $attributes): void {
    expect(fn () => storedDelivery($this, $attributes))->toThrow(QueryException::class);
})->with([
    'reminder without number' => [['kind' => 'reminder']],
    'reminder number zero' => [['kind' => 'reminder', 'reminder_number' => 0]],
    'reminder without incident' => [['kind' => 'reminder', 'reminder_number' => 1, 'incident_id' => null]],
    'down with a number' => [['kind' => 'down', 'reminder_number' => 1]],
]);

it('keeps one flapping alert per channel, monitor, kind and episode, without incident', function (): void {
    $episode = ['monitor_id' => $this->incident->monitor_id, 'incident_id' => null, 'flapping_since' => '2027-03-10 12:04:00+00'];
    storedDelivery($this, ['kind' => 'flapping_started', ...$episode]);
    storedDelivery($this, ['kind' => 'flapping_ended', ...$episode]);
    storedDelivery($this, ['kind' => 'flapping_started', ...$episode, 'flapping_since' => '2027-03-10 13:00:00+00']);

    expect(fn () => storedDelivery($this, ['kind' => 'flapping_started', ...$episode]))->toThrow(UniqueConstraintViolationException::class);
});

it('refuses a flapping alert without its monitor or episode, or with an incident', function (array $attributes): void {
    expect(fn () => storedDelivery($this, $attributes))->toThrow(QueryException::class);
})->with([
    'without episode' => [['kind' => 'flapping_started', 'incident_id' => null, 'monitor_id' => null]],
    'without monitor' => [['kind' => 'flapping_ended', 'incident_id' => null, 'flapping_since' => '2027-03-10 12:04:00+00']],
    'episode on a down alert' => [['kind' => 'down', 'flapping_since' => '2027-03-10 12:04:00+00']],
]);

it('refuses a flapping alert tied to an incident', function (): void {
    expect(fn () => storedDelivery($this, ['kind' => 'flapping_started', 'monitor_id' => $this->incident->monitor_id, 'flapping_since' => '2027-03-10 12:04:00+00']))
        ->toThrow(QueryException::class);
});
