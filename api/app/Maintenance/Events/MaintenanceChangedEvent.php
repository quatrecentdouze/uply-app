<?php

declare(strict_types=1);

namespace App\Maintenance\Events;

use App\Maintenance\Enums\MaintenanceChange;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class MaintenanceChangedEvent implements ShouldDispatchAfterCommit
{
    public array $monitorIds;

    public function __construct(
        public string $windowId,
        public string $organizationId,
        public MaintenanceChange $change,
        array $monitorIds,
    ) {
        $ids = array_values(array_unique(array_filter($monitorIds, 'is_string')));
        sort($ids);
        $this->monitorIds = $ids;
    }
}
