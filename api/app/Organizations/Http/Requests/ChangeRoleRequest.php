<?php

declare(strict_types=1);

namespace App\Organizations\Http\Requests;

use App\Organizations\Contracts\Role;
use App\Organizations\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeRoleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::enum(Role::class)],
        ];
    }

    public function role(): Role
    {
        return Role::from($this->string('role')->toString());
    }

    public function currentUser(): User
    {
        $user = $this->user();

        return $user instanceof User ? $user : throw new AuthenticationException;
    }
}
