<?php

declare(strict_types=1);

namespace App\Organizations\Actions;

use App\Organizations\Models\User;
use Illuminate\Contracts\Validation\Factory;
use Laravel\Fortify\Contracts\CreatesNewUsers;

final readonly class CreateUser implements CreatesNewUsers
{
    public function __construct(
        private Factory $validation,
        private AccountRules $rules,
    ) {}

    public function create(array $input): User
    {
        $input['email'] = AccountRules::normalizedEmail($input['email'] ?? null);
        $data = $this->validation->make($input, [
            'name' => $this->rules->name(),
            'email' => $this->rules->newEmail(),
            'password' => $this->rules->newPassword(),
        ])->validate();

        return User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
    }
}
