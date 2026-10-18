<?php

declare(strict_types=1);

use App\Alerting\Mail\AlertMail;
use App\Alerting\Models\AlertChannel;
use App\Monitoring\Probe\HostResolver;
use App\Monitoring\Storage\CheckStorageMaintenance;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use App\StatusPages\Models\StatusPage;
use App\StatusPages\Models\StatusPageMonitor;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Support\Members;
use Tests\Support\StaticHostResolver;

const WALK_TOKEN = 'walk-secret-token';

const WALK_BODY = '{"walk":"walk-secret-body"}';

function leaksOf(string $haystack): array
{
    return array_values(array_filter([WALK_TOKEN, 'walk-secret-body'], fn (string $secret): bool => str_contains($haystack, $secret)));
}

function walkedText(TestResponse $response): string
{
    return (string) $response->assertSuccessful()->getContent();
}

it('never lets a header value or the body of a monitor out of the api', function (): void {
    $clock = freezeClock('2027-03-10 12:00:00');
    app(CheckStorageMaintenance::class)->run($clock->now());
    app()->instance(HostResolver::class, new StaticHostResolver);
    Mail::fake();
    Queue::fake([BroadcastEvent::class]);
    $logged = [];
    $dispatched = [];
    Event::listen(MessageLogged::class, function (MessageLogged $log) use (&$logged): void {
        $logged[] = $log->message.json_encode($log->context);
    });
    Event::listen('App\*', function (string $name, array $payload) use (&$dispatched): void {
        $dispatched[] = $name.serialize($payload);
    });
    Http::fake(['hooks.example.com/*' => Http::sequence()
        ->pushResponse(fn () => throw new ConnectionException('cURL error 7: failed with Bearer '.WALK_TOKEN.' and '.WALK_BODY))
        ->pushResponse(fn () => throw new ConnectionException('cURL error 7: failed with Bearer '.WALK_TOKEN.' and '.WALK_BODY))
        ->push('ok', 200)
        ->push('ok', 200)]);

    $organization = Organization::factory()->create();
    $member = Members::seat($organization, Role::Member);
    $base = "/api/v1/organizations/{$organization->id}";
    $texts = [];
    $created = $this->actingAs($member)->postJson("{$base}/monitors", [
        'name' => 'Hook',
        'url' => 'https://hooks.example.com/ping',
        'method' => 'post',
        'interval_seconds' => 60,
        'request_headers' => [['name' => 'Authorization', 'value' => 'Bearer '.WALK_TOKEN]],
        'request_body' => WALK_BODY,
    ]);
    $texts[] = walkedText($created);
    $monitorId = (string) $created->json('data.id');
    $channel = AlertChannel::factory()->create(['organization_id' => $organization->id, 'settings' => [AlertChannel::RECIPIENTS_SETTING => ['ops@example.com']]]);
    DB::table('alert_channel_monitor')->insert(['alert_channel_id' => $channel->id, 'monitor_id' => $monitorId]);
    $page = StatusPage::factory()->create(['organization_id' => $organization->id, 'is_published' => true]);
    StatusPageMonitor::query()->create(['status_page_id' => $page->id, 'monitor_id' => $monitorId, 'display_name' => 'Hook', 'position' => 0]);

    foreach (['12:00:00', '12:00:20', '12:01:20', '12:01:40'] as $time) {
        $clock->moveTo($clock->now()->setTimeFromTimeString($time));
        $this->artisan('checks:dispatch')->assertSuccessful();
    }
    Http::assertSent(fn (Request $request): bool => $request->header('Authorization') === ['Bearer '.WALK_TOKEN] && $request->body() === WALK_BODY);

    $incidentId = (string) DB::table('incidents')->where('monitor_id', $monitorId)->value('id');
    expect($incidentId)->not->toBe('');
    foreach ([
        "{$base}/monitors",
        "{$base}/monitors/{$monitorId}",
        "{$base}/monitors/{$monitorId}/checks",
        "{$base}/monitors/{$monitorId}/stats?window=24h",
        "{$base}/monitors/{$monitorId}/alert-channels",
        "{$base}/overview?window=24h",
        "{$base}/incidents",
        "{$base}/incidents/{$incidentId}",
        "/api/v1/public/status-pages/{$page->slug}",
        '/api/v1/meta',
    ] as $route) {
        $texts[] = walkedText($this->getJson($route));
    }
    $texts[] = walkedText($this->patchJson("{$base}/monitors/{$monitorId}", ['name' => 'Hook renamed']));
    foreach (Queue::pushed(BroadcastEvent::class) as $broadcast) {
        $texts[] = serialize($broadcast);
    }
    Mail::assertSent(AlertMail::class);
    foreach (Mail::sent(AlertMail::class) as $mail) {
        $texts[] = $mail->render();
    }
    foreach (['check_results', 'incidents', 'incident_events', 'alert_deliveries', 'jobs', 'failed_jobs', 'monitors'] as $table) {
        $texts[] = DB::table($table)->get()->toJson();
    }
    $errorMessages = DB::table('check_results')->whereNotNull('error_message')->pluck('error_message')->all();

    expect(leaksOf(implode("\n", [...$texts, ...$logged, ...$dispatched])))->toBe([])
        ->and($errorMessages)->not->toBeEmpty()
        ->and(implode("\n", $errorMessages))->toContain('[secret]')
        ->and(Queue::pushed(BroadcastEvent::class))->not->toBeEmpty()
        ->and($dispatched)->not->toBeEmpty();
});
