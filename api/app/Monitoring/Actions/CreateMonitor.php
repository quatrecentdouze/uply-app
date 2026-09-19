<?php

declare(strict_types=1);

namespace App\Monitoring\Actions;

use App\Monitoring\Enums\MonitorChange;
use App\Monitoring\Enums\MonitoringError;
use App\Monitoring\Events\MonitorChangedEvent;
use App\Monitoring\Models\Monitor;
use App\Monitoring\Settings\MonitoringSettings;
use App\Monitoring\Values\MonitorDraft;
use App\Shared\Database\AdvisoryLock;
use App\Shared\Errors\DomainError;

final readonly class CreateMonitor
{
    private const string LOCK_SCOPE = 'monitors';

    public function __construct(
        private MonitorChangeContext $context,
        private AdvisoryLock $lock,
        private MonitoringSettings $settings,
    ) {}

    public function __invoke(string $organizationId, MonitorDraft $draft): Monitor
    {
        return $this->context->database->transaction(function () use ($organizationId, $draft): Monitor {
            $this->lock->holdUntilCommit(self::LOCK_SCOPE, $organizationId);
            $max = $this->settings->limits->maxMonitorsPerOrganization;
            if (Monitor::query()->where('organization_id', $organizationId)->count() >= $max) {
                throw new DomainError(MonitoringError::MonitorLimitReached, ['max' => $max]);
            }
            $monitor = new Monitor;
            $monitor->organization_id = $organizationId;
            $monitor->fillFrom($draft);
            $monitor->is_paused = false;
            $monitor->next_check_at = $this->context->clock->now();
            $monitor->save();
            $this->context->events->dispatch(new MonitorChangedEvent($monitor->id, $organizationId, MonitorChange::Created));

            return $monitor->refresh();
        });
    }
}
