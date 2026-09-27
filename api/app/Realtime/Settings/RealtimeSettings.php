<?php

declare(strict_types=1);

namespace App\Realtime\Settings;

final readonly class RealtimeSettings
{
    public function __construct(
        public ClientConnection $client,
    ) {}
}
