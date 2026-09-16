<?php

declare(strict_types=1);

namespace App\Organizations\Actions;

use App\Organizations\Enums\OrganizationError;
use App\Organizations\Models\Membership;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;
use App\Shared\Clock;
use App\Shared\Errors\DomainError;
use Illuminate\Database\ConnectionInterface;
use SensitiveParameter;

final readonly class AcceptInvitation
{
    public function __construct(
        private ConnectionInterface $database,
        private InvitationLookup $invitations,
        private Clock $clock,
    ) {}

    public function __invoke(User $user, #[SensitiveParameter] string $token): Membership
    {
        return $this->database->transaction(function () use ($user, $token): Membership {
            $invitation = $this->invitations->pendingForUpdate($token);
            Organization::query()->whereKey($invitation->organization_id)->lockForUpdate()->sole();

            if (mb_strtolower($invitation->email) !== mb_strtolower($user->email)) {
                throw new DomainError(OrganizationError::InvitationEmailMismatch);
            }
            $alreadyMember = Membership::query()
                ->where('organization_id', $invitation->organization_id)
                ->where('user_id', $user->id)
                ->exists();
            if ($alreadyMember) {
                throw new DomainError(OrganizationError::AlreadyMember);
            }

            $membership = Membership::query()->create([
                'organization_id' => $invitation->organization_id,
                'user_id' => $user->id,
                'role' => $invitation->role,
            ]);
            $invitation->update(['accepted_at' => $this->clock->now()]);

            return $membership;
        });
    }
}
