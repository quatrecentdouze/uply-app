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

final readonly class DeleteMaintenanceWindow
{
    public function __construct(private WindowChangeContext $context) {}

    public function __invoke(MaintenanceWindow $window): void
    {
        $this->context->database->transaction(function () use ($window): void {
            $locked = MaintenanceWindow::query()->whereKey($window->id)->lockForUpdate()->with('monitors')->firstOrFail();
            $state = MaintenanceRules::state($locked->startsAt(), $locked->endsAt(), $this->context->clock->now());
            if ($state !== MaintenanceState::Upcoming) {
                throw new DomainError(MaintenanceError::MaintenanceStarted);
            }
            $monitorIds = $locked->monitorIds();
            $locked->delete();
            $this->context->events->dispatch(new MaintenanceChangedEvent($locked->id, $locked->organization_id, MaintenanceChange::Deleted, $monitorIds));
        });
    }
}
