<?php

declare(strict_types=1);

namespace App\Alerting\Policies;

use App\Organizations\Contracts\Ability;
use App\Organizations\Contracts\OrganizationAccess;
use App\Shared\Http\AuthenticatedUserId;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;

final readonly class AlertChannelPolicy
{
    public function __construct(private OrganizationAccess $access) {}

    public function view(Authenticatable $user, string $organizationId): Response
    {
        return $this->decide($user, $organizationId, Ability::AlertChannelsView);
    }

    public function manage(Authenticatable $user, string $organizationId): Response
    {
        return $this->decide($user, $organizationId, Ability::AlertChannelsManage);
    }

    public function viewLinks(Authenticatable $user, string $organizationId): Response
    {
        return $this->decide($user, $organizationId, Ability::MonitorsView);
    }

    public function manageLinks(Authenticatable $user, string $organizationId): Response
    {
        return $this->decide($user, $organizationId, Ability::MonitorsManage);
    }

    private function decide(Authenticatable $user, string $organizationId, Ability $ability): Response
    {
        return $this->access->check(AuthenticatedUserId::of($user), $organizationId, $ability)->toPolicyResponse();
    }
}
