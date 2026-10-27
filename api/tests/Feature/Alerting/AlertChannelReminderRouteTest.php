<?php

declare(strict_types=1);

use App\Alerting\Models\AlertChannel;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use Tests\Support\Members;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->channelsUrl = "/api/v1/organizations/{$this->organization->id}/alert-channels";
    $this->actingAs(Members::seat($this->organization, Role::Admin));
});

it('creates a channel with reminders every allowed interval', function (int $minutes): void {
    $this->postJson($this->channelsUrl, ['type' => 'email', 'name' => 'Ops mail', 'recipients' => ['ops@example.com'], 'reminder_interval_minutes' => $minutes])
        ->assertCreated()
        ->assertJsonPath('data.reminder_interval_minutes', $minutes);

    expect(AlertChannel::query()->sole()->reminder_interval_minutes)->toBe($minutes);
})->with([15, 30, 60, 120, 240, 720, 1440]);

it('keeps reminders off by default and on a null interval', function (?int $minutes): void {
    $payload = ['type' => 'email', 'name' => 'Ops mail', 'recipients' => ['ops@example.com']];

    $this->postJson($this->channelsUrl, $minutes === null ? [...$payload, 'reminder_interval_minutes' => null] : $payload)
        ->assertCreated()
        ->assertJsonPath('data.reminder_interval_minutes', null);
})->with(['sent as null' => null, 'not sent' => 0]);

it('refuses an interval that is not allowed', function (mixed $minutes): void {
    $this->postJson($this->channelsUrl, ['type' => 'email', 'name' => 'Ops mail', 'recipients' => ['ops@example.com'], 'reminder_interval_minutes' => $minutes])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reminder_interval_minutes');
})->with([0, 5, 45, 2880, 'hourly']);

it('turns reminders on, keeps them on a patch without them and turns them off', function (): void {
    $channel = AlertChannel::factory()->create(['organization_id' => $this->organization->id]);
    $channelUrl = "{$this->channelsUrl}/{$channel->id}";

    $this->patchJson($channelUrl, ['reminder_interval_minutes' => 60])->assertOk()->assertJsonPath('data.reminder_interval_minutes', 60);
    $this->patchJson($channelUrl, ['name' => 'Renamed'])->assertOk()->assertJsonPath('data.reminder_interval_minutes', 60);
    $this->getJson($channelUrl)->assertOk()->assertJsonPath('data.reminder_interval_minutes', 60);
    $this->patchJson($channelUrl, ['reminder_interval_minutes' => null])->assertOk()->assertJsonPath('data.reminder_interval_minutes', null);

    expect($channel->fresh()?->reminder_interval_minutes)->toBeNull();
});
