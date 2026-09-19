<?php

declare(strict_types=1);

namespace App\Monitoring\Actions;

use App\Shared\Clock;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

final readonly class MonitorChangeContext
{
    public function __construct(
        public ConnectionInterface $database,
        public Dispatcher $events,
        public Clock $clock,
    ) {}
}
