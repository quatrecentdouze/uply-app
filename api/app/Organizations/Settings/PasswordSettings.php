<?php

declare(strict_types=1);

namespace App\Organizations\Settings;

final readonly class PasswordSettings
{
    public function __construct(
        public int $minLength,
        public int $maxLength,
    ) {}
}
