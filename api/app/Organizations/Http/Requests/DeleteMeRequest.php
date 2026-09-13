<?php

declare(strict_types=1);

namespace App\Organizations\Http\Requests;

use App\Organizations\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteMeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'current_password'],
        ];
    }

    public function currentUser(): User
    {
        $user = $this->user();

        return $user instanceof User ? $user : throw new AuthenticationException;
    }
}
