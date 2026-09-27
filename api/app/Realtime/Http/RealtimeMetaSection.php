<?php

declare(strict_types=1);

namespace App\Realtime\Http;

use App\Realtime\Settings\RealtimeSettings;
use App\Shared\Meta\MetaSection;

final readonly class RealtimeMetaSection implements MetaSection
{
    public function __construct(private RealtimeSettings $settings) {}

    public function key(): string
    {
        return 'realtime';
    }

    public function describe(): array
    {
        $client = $this->settings->client;

        return [
            'key' => $client->key,
            'host' => $client->host,
            'port' => $client->port,
            'scheme' => $client->scheme,
        ];
    }
}
