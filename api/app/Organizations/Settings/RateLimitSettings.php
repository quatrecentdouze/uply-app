<?php

declare(strict_types=1);

namespace App\Organizations\Settings;

final readonly class RateLimitSettings
{
    public function __construct(
        public int $loginPerMinute,
        public int $twoFactorPerMinute,
    ) {}
}
