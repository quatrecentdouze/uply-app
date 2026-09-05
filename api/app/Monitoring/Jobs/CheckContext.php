<?php

declare(strict_types=1);

namespace App\Monitoring\Jobs;

use App\Monitoring\Probe\Probe;
use App\Monitoring\Storage\CheckableMonitorQuery;
use App\Monitoring\Storage\CheckRecorder;
use App\Shared\Clock;

final readonly class CheckContext
{
    public function __construct(
        public CheckableMonitorQuery $monitors,
        public Probe $probe,
        public CheckRecorder $recorder,
        public Clock $clock,
    ) {}
}
