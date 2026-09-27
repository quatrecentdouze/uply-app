<?php

declare(strict_types=1);

namespace App\Realtime\Settings;

use App\Shared\Settings\SettingsReader;

final class RealtimeSettingsParser
{
    private const int MAX_PORT = 65535;

    private const array SCHEMES = ['http', 'https'];

    public function parse(string $source, array $values): RealtimeSettings
    {
        $reader = new SettingsReader($source, $values);
        $port = $reader->integer('client.port', 1);
        if ($port > self::MAX_PORT) {
            throw $reader->invalid('client.port', 'must not exceed '.self::MAX_PORT);
        }
        $scheme = $reader->text('client.scheme');
        if (! in_array($scheme, self::SCHEMES, true)) {
            throw $reader->invalid('client.scheme', 'must be one of '.implode(', ', self::SCHEMES));
        }

        return new RealtimeSettings(
            client: new ClientConnection(
                key: $reader->optionalText('client.key'),
                host: $reader->text('client.host'),
                port: $port,
                scheme: $scheme,
            ),
        );
    }
}
