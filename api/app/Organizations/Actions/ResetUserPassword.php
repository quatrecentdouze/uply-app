<?php

declare(strict_types=1);

namespace App\Organizations\Actions;

use App\Organizations\Models\User;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

final readonly class ResetUserPassword implements ResetsUserPasswords
{
    private const int REMEMBER_TOKEN_LENGTH = 60;

    public function __construct(
        private Factory $validation,
        private AccountRules $rules,
    ) {}

    public function reset(User $user, array $input): void
    {
        $data = $this->validation->make($input, ['password' => $this->rules->newPassword()])->validate();

        $user->forceFill([
            'password' => $data['password'],
            'remember_token' => Str::random(self::REMEMBER_TOKEN_LENGTH),
        ])->save();
    }
}
