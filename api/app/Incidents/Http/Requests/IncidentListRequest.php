<?php

declare(strict_types=1);

namespace App\Incidents\Http\Requests;

use App\Incidents\Enums\IncidentState;
use App\Shared\Settings\PaginationSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IncidentListRequest extends FormRequest
{
    public function rules(PaginationSettings $pagination): array
    {
        return [
            'state' => ['nullable', 'string', Rule::enum(IncidentState::class)],
            'monitor' => ['nullable', 'string', 'ulid'],
            'per_page' => $pagination->perPageRules(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function state(): ?IncidentState
    {
        $state = $this->validated('state');

        return is_string($state) ? IncidentState::from($state) : null;
    }

    public function monitorId(): ?string
    {
        $monitor = $this->validated('monitor');

        return is_string($monitor) ? strtolower($monitor) : null;
    }

    public function perPage(PaginationSettings $pagination): int
    {
        $requested = $this->validated('per_page');

        return $pagination->perPage(is_numeric($requested) ? (int) $requested : null);
    }
}
