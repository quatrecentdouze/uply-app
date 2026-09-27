<?php

declare(strict_types=1);

use App\Realtime\Settings\RealtimeSettingsParser;
use App\Shared\Settings\InvalidSettingException;

function shippedRealtimeConfig(): array
{
    return require dirname(__DIR__, 3).'/config/realtime.php';
}

function realtimeClientConfig(array $client): array
{
    return ['client' => array_replace(['key' => 'app-key', 'host' => 'localhost', 'port' => 6001, 'scheme' => 'http'], $client)];
}

it('parses the shipped realtime file', function (): void {
    $settings = (new RealtimeSettingsParser)->parse('config/realtime.php', shippedRealtimeConfig());

    expect($settings->client->host)->toBe('localhost')
        ->and($settings->client->port)->toBe(6001)
        ->and($settings->client->scheme)->toBe('http');
});

it('keeps the public app key the browser connects with', function (): void {
    $settings = (new RealtimeSettingsParser)->parse('config/realtime.php', realtimeClientConfig(['key' => 'app-key', 'scheme' => 'https', 'port' => 443]));

    expect($settings->client->key)->toBe('app-key')
        ->and($settings->client->scheme)->toBe('https')
        ->and($settings->client->port)->toBe(443);
});

it('accepts a missing app key', function (): void {
    $settings = (new RealtimeSettingsParser)->parse('config/realtime.php', realtimeClientConfig(['key' => null]));

    expect($settings->client->key)->toBeNull();
});

it('names the file and the key of a wrong value', function (array $client, string $key): void {
    expect(fn () => (new RealtimeSettingsParser)->parse('config/realtime.php', realtimeClientConfig($client)))
        ->toThrow(InvalidSettingException::class, 'config/realtime.php')
        ->toThrow(InvalidSettingException::class, $key);
})->with([
    'empty key' => [['key' => ''], 'client.key'],
    'no host' => [['host' => ''], 'client.host'],
    'port zero' => [['port' => 0], 'client.port'],
    'port too high' => [['port' => 70000], 'client.port'],
    'port as text' => [['port' => '6001'], 'client.port'],
    'ftp scheme' => [['scheme' => 'ftp'], 'client.scheme'],
]);
