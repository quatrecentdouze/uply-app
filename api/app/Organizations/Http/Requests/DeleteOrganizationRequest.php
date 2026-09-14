<?php

declare(strict_types=1);

namespace App\Organizations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DeleteOrganizationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'current_password'],
        ];
    }
}
