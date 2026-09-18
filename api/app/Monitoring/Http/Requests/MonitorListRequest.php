<?php

declare(strict_types=1);

namespace App\Monitoring\Http\Requests;

use App\Monitoring\Contracts\MonitorStatus;
use App\Monitoring\Settings\MonitoringSettings;
use App\Shared\Settings\PaginationSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MonitorListRequest extends FormRequest
{
    public function rules(PaginationSettings $pagination, MonitoringSettings $settings): array
    {
        return [
            'state' => ['nullable', 'string', Rule::enum(MonitorStatus::class)],
            'search' => ['nullable', 'string', 'max:'.$settings->limits->urlMaxLength],
            'per_page' => $pagination->perPageRules(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function status(): ?MonitorStatus
    {
        $state = $this->validated('state');

        return is_string($state) ? MonitorStatus::from($state) : null;
    }

    public function search(): ?string
    {
        $search = $this->validated('search');

        return is_string($search) && trim($search) !== '' ? trim($search) : null;
    }

    public function perPage(PaginationSettings $pagination): int
    {
        $requested = $this->validated('per_page');

        return $pagination->perPage(is_numeric($requested) ? (int) $requested : null);
    }
}
