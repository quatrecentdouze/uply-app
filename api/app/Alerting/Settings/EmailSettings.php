<?php

declare(strict_types=1);

namespace App\Alerting\Settings;

final readonly class EmailSettings
{
    public function __construct(
        public int $minRecipients,
        public int $maxRecipients,
    ) {}
}
