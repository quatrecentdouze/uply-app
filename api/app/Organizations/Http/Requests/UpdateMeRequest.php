<?php

declare(strict_types=1);

namespace App\Organizations\Http\Requests;

use App\Organizations\Actions\AccountRules;
use App\Organizations\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateMeRequest extends FormRequest
{
    public function rules(AccountRules $rules): array
    {
        return [
            'name' => ['sometimes', ...$rules->name()],
            'email' => ['sometimes', ...$rules->newEmail($this->currentUser()->id)],
            'current_password' => [Rule::requiredIf($this->changesEmail()), 'nullable', 'string', 'current_password'],
        ];
    }

    public function newName(): ?string
    {
        $name = $this->validated('name');

        return is_string($name) ? $name : null;
    }

    public function newEmail(): ?string
    {
        $email = $this->validated('email');

        return is_string($email) ? $email : null;
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

    private function changesEmail(): bool
    {
        $email = $this->input('email');

        return is_string($email) && $email !== $this->currentUser()->email;
    }
}
