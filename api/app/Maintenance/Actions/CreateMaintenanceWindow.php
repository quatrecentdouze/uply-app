<?php

declare(strict_types=1);

namespace App\Maintenance\Actions;

use App\Maintenance\Enums\MaintenanceChange;
use App\Maintenance\Enums\MaintenanceError;
use App\Maintenance\Events\MaintenanceChangedEvent;
use App\Maintenance\Models\MaintenanceWindow;
use App\Maintenance\Rules\MaintenanceRules;
use App\Maintenance\Settings\MaintenanceSettings;
use App\Maintenance\Values\WindowDraft;
use App\Shared\Database\AdvisoryLock;
use App\Shared\Errors\DomainError;
use LogicException;

final readonly class CreateMaintenanceWindow
{
    private const string LOCK_SCOPE = 'maintenance-windows';

    public function __construct(
        private WindowChangeContext $context,
        private AdvisoryLock $lock,
        private MaintenanceSettings $settings,
    ) {}

    public function __invoke(string $organizationId, WindowDraft $draft, ?string $authorId): MaintenanceWindow
    {
        return $this->context->database->transaction(function () use ($organizationId, $draft, $authorId): MaintenanceWindow {
            $this->lock->holdUntilCommit(self::LOCK_SCOPE, $organizationId);
            $now = $this->context->clock->now();
            $max = $this->settings->limits->maxOpenWindowsPerOrganization;
            if (MaintenanceWindow::query()->where('organization_id', $organizationId)->where('ends_at', '>', $now)->count() >= $max) {
                throw new DomainError(MaintenanceError::MaintenanceLimitReached, ['max' => $max]);
            }
            $window = new MaintenanceWindow;
            $window->organization_id = $organizationId;
            $window->name = $draft->name ?? throw new LogicException('a new maintenance window needs a name');
            $window->starts_at = $draft->startsAt ?? MaintenanceRules::moment($now);
            $window->ends_at = $draft->endsAt ?? throw new LogicException('a new maintenance window needs an end');
            $window->created_by_user_id = $authorId;
            $window->save();
            $this->context->monitors->replace($window, $draft->monitorIds ?? []);
            $window->load('monitors');
            $this->context->events->dispatch(new MaintenanceChangedEvent($window->id, $organizationId, MaintenanceChange::Created, $window->monitorIds()));

            return $window;
        });
    }
}
