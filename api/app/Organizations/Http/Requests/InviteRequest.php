<?php

declare(strict_types=1);

namespace App\Organizations\Http\Requests;

use App\Organizations\Actions\AccountRules;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InviteRequest extends FormRequest
{
    private const int EMAIL_MAX_LENGTH = 255;

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:'.self::EMAIL_MAX_LENGTH],
            'role' => ['required', 'string', Rule::enum(Role::class)],
        ];
    }

    public function email(): string
    {
        return $this->string('email')->toString();
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

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => AccountRules::normalizedEmail($this->input('email'))]);
        }
    }
}
