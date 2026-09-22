<?php

declare(strict_types=1);

use App\Alerting\Enums\AlertingError;
use App\Alerting\Jobs\SendAlertJob;
use App\Alerting\Models\AlertChannel;
use App\Alerting\Models\AlertDelivery;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Members;

function testChannelUrl(AlertChannel $channel): string
{
    return "/api/v1/organizations/{$channel->organization_id}/alert-channels/{$channel->id}/test";
}

beforeEach(function (): void {
    freezeClock('2027-03-10 12:00:00');
    Queue::fake([SendAlertJob::class]);
    $this->organization = Organization::factory()->create();
    $this->channel = AlertChannel::factory()->disabled()->create(['organization_id' => $this->organization->id]);
});

it('queues a test alert and answers with its delivery for the roles that manage channels', function (Role $role): void {
    $response = $this->actingAs(Members::seat($this->organization, $role))->postJson(testChannelUrl($this->channel))->assertAccepted();

    $delivery = AlertDelivery::query()->sole();
    expect($response->json('data.delivery_id'))->toBe($delivery->id)
        ->and($delivery->kind->value)->toBe('test')
        ->and($delivery->alert_channel_id)->toBe($this->channel->id)
        ->and($this->channel->fresh()?->is_enabled)->toBeFalse();
    Queue::assertPushedOn('alerts', SendAlertJob::class, fn (SendAlertJob $job): bool => $job->deliveryId === $delivery->id);
})->with([Role::Owner, Role::Admin]);

it('answers 429 with the wait over the test limit of the channel', function (): void {
    $this->actingAs(Members::seat($this->organization, Role::Owner));
    foreach (range(1, 5) as $send) {
        $this->postJson(testChannelUrl($this->channel))->assertAccepted();
    }

    $this->postJson(testChannelUrl($this->channel))
        ->assertTooManyRequests()
        ->assertHeader('Retry-After')
        ->assertJsonPath('code', 'test_limit_reached')
        ->assertJsonPath('message', __(AlertingError::TestLimitReached->messageKey(), ['seconds' => 60]));

    expect(AlertDelivery::query()->count())->toBe(5);
});

it('refuses the roles that do not manage channels and the channels of another organization', function (): void {
    $foreign = AlertChannel::factory()->create();

    $this->actingAs(Members::seat($this->organization, Role::Member))->postJson(testChannelUrl($this->channel))->assertForbidden();
    $this->actingAs(Members::outsider())->postJson(testChannelUrl($this->channel))->assertNotFound();
    $this->actingAs(Members::seat($this->organization, Role::Owner))
        ->postJson("/api/v1/organizations/{$this->organization->id}/alert-channels/{$foreign->id}/test")
        ->assertNotFound();

    expect(AlertDelivery::query()->count())->toBe(0);
});

it('has a text for every alerting error', function (AlertingError $error): void {
    expect(__($error->messageKey()))->toBeString()->not->toBe($error->messageKey())
        ->and($error->httpStatus())->toBe(429);
})->with(AlertingError::cases());
