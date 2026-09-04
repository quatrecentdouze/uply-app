<?php

declare(strict_types=1);

namespace App\Monitoring\Probe;

use App\Monitoring\Values\HostResolution;

interface HostResolver
{
    public function resolve(string $host): HostResolution;
}
