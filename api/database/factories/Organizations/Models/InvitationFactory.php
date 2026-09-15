<?php

declare(strict_types=1);

namespace Database\Factories\Organizations\Models;

use App\Organizations\Contracts\Role;
use App\Organizations\Models\Invitation;
use App\Organizations\Models\Organization;
use App\Organizations\Rules\InvitationTokenRules;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

final class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => Role::Member,
            'token_hash' => InvitationTokenRules::hash(Str::random(40)),
            'invited_by_user_id' => null,
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
        ];
    }

    public function withToken(string $token): self
    {
        return $this->state(fn (): array => ['token_hash' => InvitationTokenRules::hash($token)]);
    }

    public function role(Role $role): self
    {
        return $this->state(fn (): array => ['role' => $role]);
    }

    public function expired(): self
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }

    public function accepted(): self
    {
        return $this->state(fn (): array => ['accepted_at' => now()]);
    }
}
