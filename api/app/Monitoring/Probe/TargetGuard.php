<?php

declare(strict_types=1);

namespace App\Monitoring\Probe;

use App\Monitoring\Values\PinnedTarget;

interface TargetGuard
{
    public function pin(string $url): PinnedTarget;
}
