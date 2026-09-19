<?php

declare(strict_types=1);

namespace App\Monitoring\Actions;

use App\Monitoring\Enums\MonitorChange;
use App\Monitoring\Events\MonitorChangedEvent;
use App\Monitoring\Models\Monitor;

final readonly class DeleteMonitor
{
    public function __construct(private MonitorChangeContext $context) {}

    public function __invoke(Monitor $monitor): void
    {
        $this->context->database->transaction(function () use ($monitor): void {
            $locked = Monitor::query()->whereKey($monitor->id)->lockForUpdate()->first();
            if ($locked === null) {
                return;
            }
            $locked->delete();
            $this->context->events->dispatch(new MonitorChangedEvent($locked->id, $locked->organization_id, MonitorChange::Deleted));
        });
    }
}
