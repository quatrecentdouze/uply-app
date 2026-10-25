<?php

declare(strict_types=1);

namespace App\Maintenance\Actions;

use App\Maintenance\Storage\WindowMonitorWriter;
use App\Shared\Clock;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

final readonly class WindowChangeContext
{
    public function __construct(
        public ConnectionInterface $database,
        public Dispatcher $events,
        public Clock $clock,
        public WindowMonitorWriter $monitors,
    ) {}
}
