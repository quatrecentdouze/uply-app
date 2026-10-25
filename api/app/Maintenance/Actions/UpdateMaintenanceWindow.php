<?php

declare(strict_types=1);

namespace App\Maintenance\Actions;

use App\Maintenance\Enums\MaintenanceChange;
use App\Maintenance\Enums\MaintenanceError;
use App\Maintenance\Enums\MaintenanceState;
use App\Maintenance\Events\MaintenanceChangedEvent;
use App\Maintenance\Models\MaintenanceWindow;
use App\Maintenance\Rules\MaintenanceRules;
use App\Maintenance\Values\WindowDraft;
use App\Shared\Errors\DomainError;

final readonly class UpdateMaintenanceWindow
{
    public function __construct(private WindowChangeContext $context) {}

    public function __invoke(MaintenanceWindow $window, WindowDraft $draft): MaintenanceWindow
    {
        return $this->context->database->transaction(function () use ($window, $draft): MaintenanceWindow {
            $locked = MaintenanceWindow::query()->whereKey($window->id)->lockForUpdate()->with('monitors')->firstOrFail();
            $state = MaintenanceRules::state($locked->startsAt(), $locked->endsAt(), $this->context->clock->now());
            if ($state === MaintenanceState::Ended) {
                throw new DomainError(MaintenanceError::MaintenanceEnded);
            }
            if ($draft->startsAt !== null && ! $draft->startsAt->equalTo($locked->startsAt()) && $state !== MaintenanceState::Upcoming) {
                throw new DomainError(MaintenanceError::MaintenanceStarted);
            }
            $monitorsBefore = $locked->monitorIds();
            $locked->name = $draft->name ?? $locked->name;
            $locked->starts_at = $draft->startsAt ?? $locked->startsAt();
            $locked->ends_at = $draft->endsAt ?? $locked->endsAt();
            $changesMonitors = $draft->monitorIds !== null && ! self::sameIds($draft->monitorIds, $monitorsBefore);
            if (! $locked->isDirty() && ! $changesMonitors) {
                return $locked;
            }
            $locked->save();
            if ($changesMonitors) {
                $this->context->monitors->replace($locked, $draft->monitorIds ?? []);
            }
            $locked->load('monitors');
            $this->context->events->dispatch(new MaintenanceChangedEvent(
                $locked->id,
                $locked->organization_id,
                MaintenanceChange::Updated,
                [...$monitorsBefore, ...$locked->monitorIds()],
            ));

            return $locked;
        });
    }

    private static function sameIds(array $first, array $second): bool
    {
        sort($first);
        sort($second);

        return $first === $second;
    }
}
