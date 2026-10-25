<?php

declare(strict_types=1);

namespace App\Maintenance\Http\Requests;

use App\Maintenance\Rules\MaintenanceRules;
use App\Maintenance\Settings\MaintenanceSettings;
use App\Maintenance\Values\WindowDraft;
use App\Monitoring\Contracts\MonitorDirectory;
use App\Shared\Clock;
use App\Shared\Http\AuthenticatedUserId;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreMaintenanceWindowRequest extends FormRequest
{
    public function rules(MaintenanceSettings $settings, MonitorDirectory $monitors): array
    {
        $organization = $this->route('organization');

        return (new MaintenanceWindowFields($settings, $monitors))->rules($this, is_string($organization) ? $organization : '', partial: false);
    }

    public function after(MaintenanceSettings $settings, MonitorDirectory $monitors, Clock $clock): array
    {
        return [
            function (Validator $validator) use ($settings, $monitors, $clock): void {
                if ($validator->errors()->hasAny(['starts_at', 'ends_at'])) {
                    return;
                }
                $draft = $this->draft($settings, $monitors);
                if ($draft->endsAt === null) {
                    return;
                }
                $now = $clock->now();
                PeriodCheck::report($validator, $draft->startsAt ?? MaintenanceRules::moment($now), $draft->endsAt, $now, $draft->startsAt !== null, $settings->bounds);
            },
        ];
    }

    public function draft(MaintenanceSettings $settings, MonitorDirectory $monitors): WindowDraft
    {
        return (new MaintenanceWindowFields($settings, $monitors))->draft($this);
    }

    public function authorId(): ?string
    {
        $user = $this->user();

        return $user === null ? null : AuthenticatedUserId::of($user);
    }
}
