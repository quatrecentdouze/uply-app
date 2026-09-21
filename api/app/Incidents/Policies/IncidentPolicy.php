<?php

declare(strict_types=1);

namespace App\Incidents\Policies;

use App\Organizations\Contracts\Ability;
use App\Organizations\Contracts\OrganizationAccess;
use App\Shared\Http\AuthenticatedUserId;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;

final readonly class IncidentPolicy
{
    public function __construct(private OrganizationAccess $access) {}

    public function view(Authenticatable $user, string $organizationId): Response
    {
        return $this->access->check(AuthenticatedUserId::of($user), $organizationId, Ability::IncidentsView)->toPolicyResponse();
    }
}
