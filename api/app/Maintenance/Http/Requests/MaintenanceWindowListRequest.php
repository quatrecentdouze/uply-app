<?php

declare(strict_types=1);

namespace App\Maintenance\Http\Requests;

use App\Maintenance\Enums\MaintenanceState;
use App\Shared\Settings\PaginationSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MaintenanceWindowListRequest extends FormRequest
{
    public function rules(PaginationSettings $pagination): array
    {
        return [
            'state' => ['nullable', 'string', Rule::enum(MaintenanceState::class)],
            'per_page' => $pagination->perPageRules(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function state(): ?MaintenanceState
    {
        $state = $this->validated('state');

        return is_string($state) ? MaintenanceState::from($state) : null;
    }

    public function perPage(PaginationSettings $pagination): int
    {
        $requested = $this->validated('per_page');

        return $pagination->perPage(is_numeric($requested) ? (int) $requested : null);
    }
}
