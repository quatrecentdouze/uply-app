<?php

declare(strict_types=1);

namespace App\Organizations\Policies;

use App\Organizations\Contracts\Ability;
use App\Organizations\Contracts\OrganizationAccess;
use App\Organizations\Models\Membership;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;
use Illuminate\Auth\Access\Response;

final readonly class MembershipPolicy
{
    public function __construct(private OrganizationAccess $access) {}

    public function viewAny(User $user, Organization $organization): Response
    {
        return $this->access->roleOf($user->id, $organization->id) === null ? Response::denyAsNotFound() : Response::allow();
    }

    public function update(User $user, Membership $membership): Response
    {
        return $this->access->check($user->id, $membership->organization_id, Ability::MembersManage)->toPolicyResponse();
    }

    public function delete(User $user, Membership $membership): Response
    {
        if ($membership->user_id === $user->id) {
            return Response::allow();
        }

        return $this->access->check($user->id, $membership->organization_id, Ability::MembersManage)->toPolicyResponse();
    }
}
