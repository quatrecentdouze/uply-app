<?php

declare(strict_types=1);

namespace App\Monitoring\Events;

use App\Monitoring\Enums\MonitorChange;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class MonitorChangedEvent implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $monitorId,
        public string $organizationId,
        public MonitorChange $change,
    ) {}
}
