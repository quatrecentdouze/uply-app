<?php

declare(strict_types=1);

namespace App\Organizations\Policies;

use App\Organizations\Contracts\Ability;
use App\Organizations\Contracts\OrganizationAccess;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;
use Illuminate\Auth\Access\Response;

final readonly class OrganizationPolicy
{
    public function __construct(private OrganizationAccess $access) {}

    public function view(User $user, Organization $organization): Response
    {
        return $this->access->roleOf($user->id, $organization->id) === null ? Response::denyAsNotFound() : Response::allow();
    }

    public function update(User $user, Organization $organization): Response
    {
        return $this->access->check($user->id, $organization->id, Ability::OrganizationManage)->toPolicyResponse();
    }

    public function delete(User $user, Organization $organization): Response
    {
        return $this->access->check($user->id, $organization->id, Ability::OrganizationDelete)->toPolicyResponse();
    }
}
