<?php

declare(strict_types=1);

namespace App\Organizations\Settings;

final readonly class OrganizationLimits
{
    public function __construct(
        public int $maxOwnedPerUser,
        public int $nameMinLength,
        public int $nameMaxLength,
        public int $slugMinLength,
        public int $slugMaxLength,
    ) {}
}
