<?php

declare(strict_types=1);

namespace App\Organizations\Settings;

final readonly class InvitationSettings
{
    public function __construct(
        public int $validDays,
        public int $maxPendingPerOrganization,
        public int $tokenLength,
    ) {}
}
