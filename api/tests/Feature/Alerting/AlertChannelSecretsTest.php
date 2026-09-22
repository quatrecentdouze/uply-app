<?php

declare(strict_types=1);

use App\Alerting\Jobs\SendAlertJob;
use App\Monitoring\Models\Monitor;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Support\Members;

const SWEEP_DISCORD_SECRET = 'sweep-discord-secret-9f2c71';

const SWEEP_SLACK_SECRET = 'sweep-slack-secret-4b8e03';

function assertNoWebhookIn(TestResponse $response): void
{
    $body = (string) $response->getContent();

    expect($body)->not->toContain(SWEEP_DISCORD_SECRET)
        ->not->toContain(SWEEP_SLACK_SECRET)
        ->not->toContain('/api/webhooks/')
        ->not->toContain('/services/')
        ->not->toContain('"settings"');
}

it('never answers a webhook url from any alert channel route', function (): void {
    freezeClock('2027-03-10 12:00:00');
    Queue::fake([SendAlertJob::class]);
    $organization = Organization::factory()->create();
    $monitor = Monitor::factory()->create(['organization_id' => $organization->id]);
    $this->actingAs(Members::seat($organization, Role::Owner));
    $channels = "/api/v1/organizations/{$organization->id}/alert-channels";
    $responses = [];

    $responses[] = $discord = $this->postJson($channels, ['type' => 'discord', 'name' => 'Discord', 'webhook_url' => 'https://discord.com/api/webhooks/1234567890/'.SWEEP_DISCORD_SECRET])->assertCreated();
    $responses[] = $slack = $this->postJson($channels, ['type' => 'slack', 'name' => 'Slack', 'webhook_url' => 'https://hooks.slack.com/services/T000/B000/'.SWEEP_SLACK_SECRET])->assertCreated();
    $discordUrl = "{$channels}/{$discord->json('data.id')}";
    $slackUrl = "{$channels}/{$slack->json('data.id')}";
    $responses[] = $this->getJson($channels)->assertOk();
    $responses[] = $this->getJson($discordUrl)->assertOk();
    $responses[] = $this->patchJson($slackUrl, ['name' => 'Team Slack'])->assertOk();
    $responses[] = $this->patchJson($slackUrl, ['webhook_url' => 'https://hooks.slack.com/services/T000/B000/'.SWEEP_SLACK_SECRET.'-next'])->assertOk();
    $responses[] = $this->patchJson($discordUrl, ['webhook_url' => 'https://evil.example.com/api/webhooks/1/'.SWEEP_DISCORD_SECRET])->assertUnprocessable();
    $responses[] = $this->postJson("{$discordUrl}/test")->assertAccepted();
    $monitorChannels = "/api/v1/organizations/{$organization->id}/monitors/{$monitor->id}/alert-channels";
    $responses[] = $this->putJson($monitorChannels, ['channel_ids' => [$discord->json('data.id'), $slack->json('data.id')]])->assertOk();
    $responses[] = $this->getJson($monitorChannels)->assertOk()->assertJsonCount(2, 'data');
    $responses[] = $this->getJson('/api/v1/meta')->assertOk();

    foreach ($responses as $response) {
        assertNoWebhookIn($response);
    }
    expect($responses)->toHaveCount(11);
});
