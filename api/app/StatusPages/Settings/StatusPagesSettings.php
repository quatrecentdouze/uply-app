<?php

declare(strict_types=1);

namespace App\StatusPages\Settings;

final readonly class StatusPagesSettings
{
    public function __construct(
        public StatusPageLimits $limits,
        public StatusPageLengths $lengths,
        public PublicPageSettings $public,
    ) {}
}
