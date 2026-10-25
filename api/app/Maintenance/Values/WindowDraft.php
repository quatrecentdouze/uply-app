<?php

declare(strict_types=1);

namespace App\Maintenance\Values;

use Carbon\CarbonImmutable;

final readonly class WindowDraft
{
    public function __construct(
        public ?string $name,
        public ?CarbonImmutable $startsAt,
        public ?CarbonImmutable $endsAt,
        public ?array $monitorIds,
    ) {}
}
