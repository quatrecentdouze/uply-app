<?php

declare(strict_types=1);

namespace App\Organizations\Storage;

use App\Organizations\Contracts\Ability;
use App\Organizations\Contracts\AccessDecision;
use App\Organizations\Contracts\OrganizationAccess;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Membership;
use App\Organizations\Settings\RoleRegistry;

final readonly class DatabaseOrganizationAccess implements OrganizationAccess
{
    public function __construct(private RoleRegistry $roles) {}

    public function roleOf(string $userId, string $organizationId): ?Role
    {
        $role = Membership::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->value('role');

        return $role instanceof Role ? $role : null;
    }

    public function check(string $userId, string $organizationId, Ability $ability): AccessDecision
    {
        $role = $this->roleOf($userId, $organizationId);
        if ($role === null) {
            return AccessDecision::NotMember;
        }

        return $this->roles->allows($role, $ability) ? AccessDecision::Allowed : AccessDecision::Forbidden;
    }
}
