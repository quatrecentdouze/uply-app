<?php

declare(strict_types=1);

namespace App\Maintenance\Actions;

use App\Maintenance\Enums\MaintenanceChange;
use App\Maintenance\Enums\MaintenanceError;
use App\Maintenance\Enums\MaintenanceState;
use App\Maintenance\Events\MaintenanceChangedEvent;
use App\Maintenance\Models\MaintenanceWindow;
use App\Maintenance\Rules\MaintenanceRules;
use App\Shared\Errors\DomainError;

final readonly class EndMaintenanceWindow
{
    public function __construct(private WindowChangeContext $context) {}

    public function __invoke(MaintenanceWindow $window): MaintenanceWindow
    {
        return $this->context->database->transaction(function () use ($window): MaintenanceWindow {
            $locked = MaintenanceWindow::query()->whereKey($window->id)->lockForUpdate()->with('monitors')->firstOrFail();
            $now = $this->context->clock->now();
            if (MaintenanceRules::state($locked->startsAt(), $locked->endsAt(), $now) !== MaintenanceState::Active) {
                throw new DomainError(MaintenanceError::MaintenanceNotActive);
            }
            $locked->ends_at = MaintenanceRules::endedAt($locked->startsAt(), $now);
            $locked->save();
            $this->context->events->dispatch(new MaintenanceChangedEvent($locked->id, $locked->organization_id, MaintenanceChange::Ended, $locked->monitorIds()));

            return $locked;
        });
    }
}
