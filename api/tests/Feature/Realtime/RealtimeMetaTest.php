<?php

declare(strict_types=1);

use App\Realtime\Settings\RealtimeSettings;

function useRealtimeClient(array $client): void
{
    config(['realtime.client' => array_replace(config('realtime.client'), $client)]);
    app()->forgetInstance(RealtimeSettings::class);
}

it('gives the browser what it needs to reach reverb', function (): void {
    useRealtimeClient(['key' => 'public-app-key']);

    $this->getJson('/api/v1/meta')
        ->assertOk()
        ->assertJsonPath('data.realtime', [
            'key' => 'public-app-key',
            'host' => 'localhost',
            'port' => 6001,
            'scheme' => 'http',
        ]);
});

it('says when reverb has no app key', function (): void {
    useRealtimeClient(['key' => null]);

    $this->getJson('/api/v1/meta')
        ->assertOk()
        ->assertJsonPath('data.realtime.key', null);
});

it('never sends the reverb secret', function (): void {
    useRealtimeClient(['key' => 'public-app-key']);
    config(['broadcasting.connections.reverb.secret' => 'reverb-secret-value', 'reverb.apps.apps.0.secret' => 'reverb-secret-value']);

    $body = $this->getJson('/api/v1/meta')->assertOk()->getContent();

    expect($body)->not->toContain('reverb-secret-value')
        ->and(array_keys(json_decode((string) $body, true)['data']['realtime']))->toBe(['key', 'host', 'port', 'scheme']);
});
