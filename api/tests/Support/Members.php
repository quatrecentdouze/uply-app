<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Organizations\Contracts\Role;
use App\Organizations\Models\Membership;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;

final class Members
{
    public static function seat(Organization $organization, Role $role): User
    {
        $user = User::factory()->create();
        Membership::factory()->for($organization)->for($user)->role($role)->create();

        return $user;
    }

    public static function outsider(): User
    {
        return self::seat(Organization::factory()->create(), Role::Owner);
    }
}
