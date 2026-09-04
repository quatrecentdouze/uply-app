<?php

declare(strict_types=1);

namespace App\Monitoring\Probe;

use App\Monitoring\Values\ProbeRequest;
use App\Monitoring\Values\ProbeResult;

interface Probe
{
    public function run(ProbeRequest $request): ProbeResult;
}
