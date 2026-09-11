<?php

declare(strict_types=1);

namespace Database\Factories\Organizations\Models;

use App\Organizations\Contracts\Role;
use App\Organizations\Models\Membership;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

final class MembershipFactory extends Factory
{
    protected $model = Membership::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'role' => Role::Member,
        ];
    }

    public function role(Role $role): self
    {
        return $this->state(fn (): array => ['role' => $role]);
    }
}
