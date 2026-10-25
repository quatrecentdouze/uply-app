<?php

declare(strict_types=1);

namespace App\Maintenance\Http\Requests;

use App\Maintenance\Rules\MaintenanceRules;
use App\Maintenance\Settings\MaintenanceSettings;
use App\Maintenance\Values\WindowDraft;
use App\Monitoring\Contracts\MonitorDirectory;
use App\Monitoring\Contracts\MonitorView;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final readonly class MaintenanceWindowFields
{
    public const int NAME_MIN_LENGTH = 1;

    public const int MONITORS_MIN = 1;

    public function __construct(
        private MaintenanceSettings $settings,
        private MonitorDirectory $monitors,
    ) {}

    public function rules(FormRequest $request, string $organizationId, bool $partial): array
    {
        $presence = $partial ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'string', 'min:'.self::NAME_MIN_LENGTH, 'max:'.$this->settings->nameMaxLength],
            'starts_at' => $partial ? ['sometimes', 'date'] : ['nullable', 'date'],
            'ends_at' => [$presence, 'date'],
            'monitor_ids' => [$presence, 'array', 'list', 'min:'.self::MONITORS_MIN, 'max:'.$this->settings->limits->maxMonitorsPerWindow],
            'monitor_ids.*' => ['required', 'string', 'ulid', 'distinct:ignore_case', new MonitorOfOrganization($this->monitorsIn($request, $organizationId))],
        ];
    }

    public function draft(FormRequest $request): WindowDraft
    {
        return new WindowDraft(
            name: $request->has('name') ? $request->string('name')->toString() : null,
            startsAt: self::time($request->input('starts_at')),
            endsAt: self::time($request->input('ends_at')),
            monitorIds: $request->has('monitor_ids') ? self::ids($request->input('monitor_ids')) : null,
        );
    }

    private function monitorsIn(FormRequest $request, string $organizationId): array
    {
        $allowed = [];
        foreach ($this->monitors->findMany(self::ids($request->input('monitor_ids'))) as $view) {
            if ($view instanceof MonitorView && $view->organizationId === $organizationId) {
                $allowed[] = $view->id;
            }
        }

        return $allowed;
    }

    private static function time(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && $value !== '' ? MaintenanceRules::moment(CarbonImmutable::parse($value)) : null;
    }

    private static function ids(mixed $values): array
    {
        $ids = [];
        foreach (is_array($values) ? $values : [] as $value) {
            if (is_string($value)) {
                $ids[] = strtolower($value);
            }
        }

        return array_values(array_unique($ids));
    }
}
