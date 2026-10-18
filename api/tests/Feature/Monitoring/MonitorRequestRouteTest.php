<?php

declare(strict_types=1);

use App\Monitoring\Events\MonitorChangedEvent;
use App\Monitoring\Models\Monitor;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\Support\Members;

beforeEach(function (): void {
    $this->clock = freezeClock('2027-03-10 12:00:00');
    $this->organization = Organization::factory()->create();
    $this->member = Members::seat($this->organization, Role::Member);
});

function monitorsRoute(Organization $organization): string
{
    return "/api/v1/organizations/{$organization->id}/monitors";
}

function hookPayload(array $changes = []): array
{
    return [...[
        'name' => 'Hook',
        'url' => 'https://hooks.example.com/ping',
        'method' => 'post',
        'request_headers' => [
            ['name' => 'Authorization', 'value' => 'Bearer route-secret-token'],
            ['name' => 'Content-Type', 'value' => 'application/json'],
        ],
        'request_body' => '{"probe":"route-secret-body"}',
    ], ...$changes];
}

function assertNoRequestSecret(TestResponse $response): void
{
    expect($response->getContent())->not->toContain('route-secret-token')
        ->and($response->getContent())->not->toContain('route-secret-body')
        ->and($response->getContent())->not->toContain('application/json');
}

function storedRequest(string $monitorId): array
{
    $request = Monitor::query()->findOrFail($monitorId)->checkRequest();

    return ['headers' => $request->toStored()['headers'] ?? [], 'body' => $request->body];
}

it('takes the headers and the body and answers only their names and presence', function (): void {
    $response = $this->actingAs($this->member)->postJson(monitorsRoute($this->organization), hookPayload())
        ->assertCreated()
        ->assertJsonPath('data.method', 'post')
        ->assertJsonPath('data.request_header_names', ['Authorization', 'Content-Type'])
        ->assertJsonPath('data.has_request_body', true);
    assertNoRequestSecret($response);
    assertNoRequestSecret($this->getJson(monitorsRoute($this->organization)));
    assertNoRequestSecret($this->getJson(monitorsRoute($this->organization).'/'.$response->json('data.id')));

    expect(storedRequest((string) $response->json('data.id')))->toBe([
        'headers' => [
            ['name' => 'Authorization', 'value' => 'Bearer route-secret-token'],
            ['name' => 'Content-Type', 'value' => 'application/json'],
        ],
        'body' => '{"probe":"route-secret-body"}',
    ]);
});

it('answers no header and no body for a plain monitor', function (): void {
    $this->actingAs($this->member)->postJson(monitorsRoute($this->organization), ['name' => 'API', 'url' => 'https://api.example.com/health'])
        ->assertCreated()
        ->assertJsonPath('data.request_header_names', [])
        ->assertJsonPath('data.has_request_body', false);
});

it('sends the body as it was typed', function (): void {
    $id = $this->actingAs($this->member)->postJson(monitorsRoute($this->organization), hookPayload(['request_body' => "  padded body\n"]))
        ->assertCreated()
        ->json('data.id');

    expect(storedRequest((string) $id)['body'])->toBe("  padded body\n");
});

it('validates the headers and the body', function (array $changes, string $field): void {
    $this->actingAs($this->member)->postJson(monitorsRoute($this->organization), hookPayload($changes))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'too many headers' => [['request_headers' => array_map(fn (int $index): array => ['name' => "X-H{$index}", 'value' => 'v'], range(1, 21))], 'request_headers'],
    'headers as an object' => [['request_headers' => ['Authorization' => 'Bearer a']], 'request_headers'],
    'header without value' => [['request_headers' => [['name' => 'X-Key']]], 'request_headers.0.value'],
    'header with another key' => [['request_headers' => [['name' => 'X-Key', 'value' => 'a', 'extra' => 'b']]], 'request_headers.0'],
    'header name not a token' => [['request_headers' => [['name' => 'X Key', 'value' => 'a']]], 'request_headers.0.name'],
    'header name too long' => [['request_headers' => [['name' => str_repeat('x', 101), 'value' => 'a']]], 'request_headers.0.name'],
    'host header' => [['request_headers' => [['name' => 'Host', 'value' => 'evil.example.com']]], 'request_headers.0.name'],
    'proxy header in capitals' => [['request_headers' => [['name' => 'PROXY-AUTHORIZATION', 'value' => 'a']]], 'request_headers.0.name'],
    'same name twice' => [['request_headers' => [['name' => 'X-Key', 'value' => 'a'], ['name' => 'x-key', 'value' => 'b']]], 'request_headers.1.name'],
    'value with a line break' => [['request_headers' => [['name' => 'X-Key', 'value' => "a\r\nHost: evil"]]], 'request_headers.0.value'],
    'value too long' => [['request_headers' => [['name' => 'X-Key', 'value' => str_repeat('v', 1001)]]], 'request_headers.0.value'],
    'body too long' => [['request_body' => str_repeat('b', 10001)], 'request_body'],
    'body with get' => [['method' => 'get'], 'request_body'],
    'body with head' => [['method' => 'head'], 'request_body'],
]);

it('keeps, replaces or clears the headers and the body on a patch', function (): void {
    $id = (string) $this->actingAs($this->member)->postJson(monitorsRoute($this->organization), hookPayload())->assertCreated()->json('data.id');
    $route = monitorsRoute($this->organization)."/{$id}";

    $this->patchJson($route, ['name' => 'Renamed hook'])->assertOk()->assertJsonPath('data.request_header_names', ['Authorization', 'Content-Type']);
    expect(storedRequest($id)['body'])->toBe('{"probe":"route-secret-body"}');

    $this->patchJson($route, ['request_headers' => [['name' => 'X-Api-Key', 'value' => 'route-secret-key']]])
        ->assertOk()
        ->assertJsonPath('data.request_header_names', ['X-Api-Key'])
        ->assertJsonPath('data.has_request_body', true);
    $this->patchJson($route, ['request_body' => 'replaced'])->assertOk()->assertJsonPath('data.has_request_body', true);
    expect(storedRequest($id)['body'])->toBe('replaced');

    $this->patchJson($route, ['request_headers' => [], 'request_body' => null])
        ->assertOk()
        ->assertJsonPath('data.request_header_names', [])
        ->assertJsonPath('data.has_request_body', false);
    expect(storedRequest($id))->toBe(['headers' => [], 'body' => null]);
});

it('refuses a method without body while the stored body stays, unless the body is cleared with it', function (): void {
    $id = (string) $this->actingAs($this->member)->postJson(monitorsRoute($this->organization), hookPayload())->assertCreated()->json('data.id');
    $route = monitorsRoute($this->organization)."/{$id}";

    $this->patchJson($route, ['method' => 'get'])->assertUnprocessable()->assertJsonValidationErrors('method');
    $this->patchJson($route, ['method' => 'get', 'request_body' => 'still here'])->assertUnprocessable()->assertJsonValidationErrors('request_body');
    $this->patchJson($route, ['method' => 'get', 'request_body' => null])->assertOk()->assertJsonPath('data.method', 'get');
});

it('checks at once and clears the counters when the headers or the body change', function (array $changes): void {
    $id = (string) $this->actingAs($this->member)->postJson(monitorsRoute($this->organization), hookPayload())->assertCreated()->json('data.id');
    Monitor::query()->whereKey($id)->update(['consecutive_failures' => 1, 'next_check_at' => $this->clock->now()->addMinutes(3)]);
    Event::fake([MonitorChangedEvent::class]);

    $this->patchJson(monitorsRoute($this->organization)."/{$id}", $changes)->assertOk();

    $monitor = Monitor::query()->findOrFail($id);
    expect($monitor->consecutive_failures)->toBe(0)
        ->and($monitor->nextCheckAt()->toIso8601ZuluString())->toBe('2027-03-10T12:00:00Z');
    Event::assertDispatchedOnce(MonitorChangedEvent::class);
})->with([
    'header value' => [['request_headers' => [['name' => 'Authorization', 'value' => 'Bearer other'], ['name' => 'Content-Type', 'value' => 'application/json']]]],
    'body' => [['request_body' => '{"probe":"other"}']],
    'method' => [['method' => 'put']],
]);

it('changes nothing when the same headers and body come back', function (): void {
    $id = (string) $this->actingAs($this->member)->postJson(monitorsRoute($this->organization), hookPayload())->assertCreated()->json('data.id');
    Event::fake([MonitorChangedEvent::class]);

    $payload = hookPayload();
    $this->patchJson(monitorsRoute($this->organization)."/{$id}", ['request_headers' => $payload['request_headers'], 'request_body' => $payload['request_body']])->assertOk();

    Event::assertNotDispatched(MonitorChangedEvent::class);
});
