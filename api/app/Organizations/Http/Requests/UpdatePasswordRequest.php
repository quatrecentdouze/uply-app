<?php

declare(strict_types=1);

namespace App\Organizations\Http\Requests;

use App\Organizations\Actions\AccountRules;
use App\Organizations\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\FormRequest;

final class UpdatePasswordRequest extends FormRequest
{
    public function rules(AccountRules $rules): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => $rules->newPassword(),
        ];
    }

    public function newPassword(): string
    {
        return $this->string('password')->toString();
    }

    public function currentUser(): User
    {
        $user = $this->user();

        return $user instanceof User ? $user : throw new AuthenticationException;
    }
}
