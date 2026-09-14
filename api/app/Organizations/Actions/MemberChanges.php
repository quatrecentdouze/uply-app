<?php

declare(strict_types=1);

namespace App\Organizations\Actions;

use App\Organizations\Contracts\OrganizationAccess;
use App\Organizations\Contracts\Role;
use App\Organizations\Enums\OrganizationError;
use App\Organizations\Models\Membership;
use App\Organizations\Models\User;
use App\Shared\Errors\DomainError;
use Illuminate\Database\ConnectionInterface;

final readonly class MemberChanges
{
    public function __construct(
        private ConnectionInterface $database,
        private OrganizationAccess $access,
    ) {}

    public function changeRole(User $actor, Membership $membership, Role $role): Membership
    {
        return $this->database->transaction(function () use ($actor, $membership, $role): Membership {
            $owners = $this->lockedOwnerCount($membership->organization_id);
            if (! $this->actsAsOwner($actor, $membership) && ($membership->role === Role::Owner || $role === Role::Owner)) {
                throw new DomainError(OrganizationError::RoleNotAllowed);
            }
            if ($membership->role === Role::Owner && $role !== Role::Owner && $owners <= 1) {
                throw new DomainError(OrganizationError::LastOwner);
            }

            $membership->update(['role' => $role]);

            return $membership;
        });
    }

    public function remove(User $actor, Membership $membership): void
    {
        $this->database->transaction(function () use ($actor, $membership): void {
            $owners = $this->lockedOwnerCount($membership->organization_id);
            $leaving = $membership->user_id === $actor->id;
            if (! $leaving && ! $this->actsAsOwner($actor, $membership) && $membership->role === Role::Owner) {
                throw new DomainError(OrganizationError::RoleNotAllowed);
            }
            if ($membership->role === Role::Owner && $owners <= 1) {
                throw new DomainError(OrganizationError::LastOwner);
            }

            $membership->delete();
        });
    }

    private function actsAsOwner(User $actor, Membership $membership): bool
    {
        return $this->access->roleOf($actor->id, $membership->organization_id) === Role::Owner;
    }

    private function lockedOwnerCount(string $organizationId): int
    {
        return count(Membership::query()
            ->where('organization_id', $organizationId)
            ->where('role', Role::Owner)
            ->lockForUpdate()
            ->pluck('id')
            ->all());
    }
}
