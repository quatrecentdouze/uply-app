<?php

declare(strict_types=1);

namespace App\Realtime\Channels;

use App\Organizations\Contracts\Ability;
use App\Organizations\Contracts\AccessDecision;
use App\Organizations\Contracts\OrganizationAccess;
use Illuminate\Contracts\Auth\Authenticatable;

final readonly class OrganizationMembersChannel
{
    public function __construct(private OrganizationAccess $access) {}

    public function join(Authenticatable $user, string $organizationId): bool
    {
        $userId = $user->getAuthIdentifier();

        return is_string($userId)
            && $this->access->check($userId, $organizationId, Ability::MonitorsView) === AccessDecision::Allowed;
    }
}
