<?php

declare(strict_types=1);

namespace App\Monitoring\Actions;

use App\Monitoring\Enums\MonitorChange;
use App\Monitoring\Events\MonitorChangedEvent;
use App\Monitoring\Models\Monitor;
use App\Monitoring\Rules\MonitorChangeRules;
use App\Monitoring\Values\MonitorDraft;

final readonly class UpdateMonitor
{
    public function __construct(private MonitorChangeContext $context) {}

    public function __invoke(Monitor $monitor, MonitorDraft $draft, ?bool $paused): Monitor
    {
        return $this->context->database->transaction(function () use ($monitor, $draft, $paused): Monitor {
            $locked = Monitor::query()->whereKey($monitor->id)->lockForUpdate()->firstOrFail();
            $before = $locked->checkDefinition();
            $locked->fillFrom($draft);
            $locked->is_paused = $paused ?? $locked->is_paused;
            $plan = MonitorChangeRules::plan($before, $locked->checkDefinition(), $locked->nextCheckAt(), $this->context->clock->now());
            if ($plan->clearsStreak) {
                $locked->clearStreak();
            }
            $locked->next_check_at = $plan->nextCheckAt;
            if (! $locked->isDirty()) {
                return $locked;
            }
            $locked->save();
            $this->context->events->dispatch(new MonitorChangedEvent($locked->id, $locked->organization_id, MonitorChange::Updated));

            return $locked;
        });
    }
}
