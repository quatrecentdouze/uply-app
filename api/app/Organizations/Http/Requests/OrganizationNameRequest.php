<?php

declare(strict_types=1);

namespace App\Organizations\Http\Requests;

use App\Organizations\Models\User;
use App\Organizations\Settings\OrganizationsSettings;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\FormRequest;

final class OrganizationNameRequest extends FormRequest
{
    public function rules(OrganizationsSettings $settings): array
    {
        return [
            'name' => $settings->organizations->nameRules(),
        ];
    }

    public function name(): string
    {
        return trim($this->string('name')->toString());
    }

    public function currentUser(): User
    {
        $user = $this->user();

        return $user instanceof User ? $user : throw new AuthenticationException;
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }
    }
}
