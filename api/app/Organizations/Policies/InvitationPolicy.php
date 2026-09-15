<?php

declare(strict_types=1);

namespace App\Organizations\Policies;

use App\Organizations\Contracts\Ability;
use App\Organizations\Contracts\OrganizationAccess;
use App\Organizations\Models\Invitation;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;
use Illuminate\Auth\Access\Response;

final readonly class InvitationPolicy
{
    public function __construct(private OrganizationAccess $access) {}

    public function viewAny(User $user, Organization $organization): Response
    {
        return $this->access->check($user->id, $organization->id, Ability::MembersManage)->toPolicyResponse();
    }

    public function create(User $user, Organization $organization): Response
    {
        return $this->access->check($user->id, $organization->id, Ability::MembersManage)->toPolicyResponse();
    }

    public function delete(User $user, Invitation $invitation): Response
    {
        return $this->access->check($user->id, $invitation->organization_id, Ability::MembersManage)->toPolicyResponse();
    }
}
