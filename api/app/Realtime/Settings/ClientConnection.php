<?php

declare(strict_types=1);

namespace App\Realtime\Settings;

final readonly class ClientConnection
{
    public function __construct(
        public ?string $key,
        public string $host,
        public int $port,
        public string $scheme,
    ) {}
}
