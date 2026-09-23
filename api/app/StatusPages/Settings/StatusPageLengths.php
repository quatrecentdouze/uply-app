<?php

declare(strict_types=1);

namespace App\StatusPages\Settings;

final readonly class StatusPageLengths
{
    public function __construct(
        public int $titleMax,
        public int $descriptionMax,
        public int $displayNameMax,
        public int $slugMin,
        public int $slugMax,
    ) {}
}
