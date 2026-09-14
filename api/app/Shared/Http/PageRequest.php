<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Settings\PaginationSettings;
use Illuminate\Foundation\Http\FormRequest;

final class PageRequest extends FormRequest
{
    public function rules(PaginationSettings $pagination): array
    {
        return [
            'per_page' => $pagination->perPageRules(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function perPage(PaginationSettings $pagination): int
    {
        $requested = $this->validated('per_page');

        return $pagination->perPage(is_numeric($requested) ? (int) $requested : null);
    }
}
