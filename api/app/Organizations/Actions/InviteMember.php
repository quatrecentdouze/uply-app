<?php

declare(strict_types=1);

namespace App\Organizations\Actions;

use App\Organizations\Contracts\OrganizationAccess;
use App\Organizations\Contracts\Role;
use App\Organizations\Enums\OrganizationError;
use App\Organizations\Models\Invitation;
use App\Organizations\Models\Membership;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;
use App\Organizations\Rules\InvitationTokenRules;
use App\Organizations\Settings\InvitationSettings;
use App\Organizations\Settings\OrganizationsSettings;
use App\Shared\Clock;
use App\Shared\Errors\DomainError;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final readonly class InviteMember
{
    private InvitationSettings $settings;

    public function __construct(
        private ConnectionInterface $database,
        private OrganizationAccess $access,
        private Clock $clock,
        OrganizationsSettings $settings,
    ) {
        $this->settings = $settings->invitations;
    }

    public function __invoke(User $inviter, Organization $organization, string $email, Role $role): IssuedInvitation
    {
        return $this->database->transaction(function () use ($inviter, $organization, $email, $role): IssuedInvitation {
            Organization::query()->whereKey($organization->id)->lockForUpdate()->sole();
            $now = $this->clock->now();

            if ($role === Role::Owner && $this->access->roleOf($inviter->id, $organization->id) !== Role::Owner) {
                throw new DomainError(OrganizationError::RoleNotAllowed);
            }
            if ($this->isMember($organization, $email)) {
                throw new DomainError(OrganizationError::AlreadyMember);
            }
            $this->replaceExpiredInvitation($organization, $email, $now);
            if ($this->pendingCount($organization, $now) >= $this->settings->maxPendingPerOrganization) {
                throw new DomainError(OrganizationError::InvitationLimitReached, ['max' => $this->settings->maxPendingPerOrganization]);
            }

            $token = Str::random($this->settings->tokenLength);
            $invitation = Invitation::query()->create([
                'organization_id' => $organization->id,
                'email' => $email,
                'role' => $role,
                'token_hash' => InvitationTokenRules::hash($token),
                'invited_by_user_id' => $inviter->id,
                'expires_at' => $now->addDays($this->settings->validDays),
            ]);

            return new IssuedInvitation($invitation, $token);
        });
    }

    private function isMember(Organization $organization, string $email): bool
    {
        return Membership::query()
            ->where('organization_id', $organization->id)
            ->whereHas('user', fn (Builder $users) => $users->whereRaw('lower(email) = ?', [mb_strtolower($email)]))
            ->exists();
    }

    private function replaceExpiredInvitation(Organization $organization, string $email, CarbonImmutable $now): void
    {
        $pending = Invitation::query()
            ->where('organization_id', $organization->id)
            ->whereNull('accepted_at')
            ->whereRaw('lower(email) = ?', [mb_strtolower($email)])
            ->first();
        if ($pending === null) {
            return;
        }
        if (! $pending->isExpiredAt($now)) {
            throw new DomainError(OrganizationError::InvitationPending);
        }
        $pending->delete();
    }

    private function pendingCount(Organization $organization, CarbonImmutable $now): int
    {
        return Invitation::query()
            ->where('organization_id', $organization->id)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', $now)
            ->count();
    }
}
