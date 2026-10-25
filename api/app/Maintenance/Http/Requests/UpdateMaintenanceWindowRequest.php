<?php

declare(strict_types=1);

namespace App\Maintenance\Http\Requests;

use App\Maintenance\Enums\MaintenanceState;
use App\Maintenance\Models\MaintenanceWindow;
use App\Maintenance\Rules\MaintenanceRules;
use App\Maintenance\Settings\MaintenanceSettings;
use App\Maintenance\Values\WindowDraft;
use App\Monitoring\Contracts\MonitorDirectory;
use App\Shared\Clock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateMaintenanceWindowRequest extends FormRequest
{
    public function rules(MaintenanceSettings $settings, MonitorDirectory $monitors): array
    {
        return (new MaintenanceWindowFields($settings, $monitors))->rules($this, $this->routeText('organization'), partial: true);
    }

    public function after(MaintenanceSettings $settings, MonitorDirectory $monitors, Clock $clock): array
    {
        return [
            function (Validator $validator) use ($settings, $monitors, $clock): void {
                if ($validator->errors()->hasAny(['starts_at', 'ends_at'])) {
                    return;
                }
                $window = MaintenanceWindow::query()->where('organization_id', $this->routeText('organization'))->whereKey($this->routeText('maintenanceWindow'))->first();
                $draft = $this->draft($settings, $monitors);
                if ($window === null || ($draft->startsAt === null && $draft->endsAt === null)) {
                    return;
                }
                $now = $clock->now();
                $state = MaintenanceRules::state($window->startsAt(), $window->endsAt(), $now);
                $movesStart = $draft->startsAt !== null && ! $draft->startsAt->equalTo($window->startsAt());
                if ($state === MaintenanceState::Ended || ($movesStart && $state !== MaintenanceState::Upcoming)) {
                    return;
                }
                PeriodCheck::report($validator, $draft->startsAt ?? $window->startsAt(), $draft->endsAt ?? $window->endsAt(), $now, $movesStart, $settings->bounds);
            },
        ];
    }

    public function draft(MaintenanceSettings $settings, MonitorDirectory $monitors): WindowDraft
    {
        return (new MaintenanceWindowFields($settings, $monitors))->draft($this);
    }

    private function routeText(string $parameter): string
    {
        $value = $this->route($parameter);

        return is_string($value) ? $value : '';
    }
}
