<?php

declare(strict_types=1);

namespace App\Organizations\Settings;

use App\Organizations\Contracts\Ability;
use App\Organizations\Contracts\Role;

final readonly class RoleRegistry
{
    public function __construct(
        private array $abilitiesByRole,
    ) {}

    public function allows(Role $role, Ability $ability): bool
    {
        $abilities = $this->abilitiesByRole[$role->value] ?? [];

        return is_array($abilities) && in_array($ability, $abilities, true);
    }

    public function abilitiesOf(Role $role): array
    {
        return array_values(array_filter(Ability::cases(), fn (Ability $ability): bool => $this->allows($role, $ability)));
    }
}
