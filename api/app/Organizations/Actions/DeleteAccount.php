<?php

declare(strict_types=1);

namespace App\Organizations\Actions;

use App\Organizations\Contracts\Role;
use App\Organizations\Enums\OrganizationError;
use App\Organizations\Models\Membership;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;
use App\Shared\Errors\DomainError;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final readonly class DeleteAccount
{
    public function __construct(private ConnectionInterface $database) {}

    public function __invoke(User $user): void
    {
        $this->database->transaction(function () use ($user): void {
            Organization::query()
                ->whereIn('id', Membership::query()->select('organization_id')->where('user_id', $user->id))
                ->lockForUpdate()
                ->get(['id']);

            if ($this->ownedAloneWithOthers($user)->exists()) {
                throw new DomainError(OrganizationError::SoleOwner);
            }

            Organization::query()
                ->whereIn('id', $this->membershipsWithoutOthers($user)->select('organization_id'))
                ->delete();
            $user->delete();
        });
    }

    private function ownedAloneWithOthers(User $user): Builder
    {
        return Membership::query()
            ->where('user_id', $user->id)
            ->where('role', Role::Owner)
            ->whereNotExists(fn (QueryBuilder $owners) => $this->otherMembers($owners, $user)->where('others.role', Role::Owner->value))
            ->whereExists(fn (QueryBuilder $members) => $this->otherMembers($members, $user));
    }

    private function membershipsWithoutOthers(User $user): Builder
    {
        return Membership::query()
            ->where('user_id', $user->id)
            ->whereNotExists(fn (QueryBuilder $members) => $this->otherMembers($members, $user));
    }

    private function otherMembers(QueryBuilder $query, User $user): QueryBuilder
    {
        return $query->selectRaw('1')
            ->from('memberships as others')
            ->whereColumn('others.organization_id', 'memberships.organization_id')
            ->where('others.user_id', '<>', $user->id);
    }
}
