<?php

declare(strict_types=1);

namespace App\Organizations\Actions;

use App\Organizations\Enums\OrganizationError;
use App\Organizations\Models\Invitation;
use App\Organizations\Rules\InvitationTokenRules;
use App\Shared\Clock;
use App\Shared\Errors\DomainError;
use SensitiveParameter;

final readonly class InvitationLookup
{
    public function __construct(private Clock $clock) {}

    public function pending(#[SensitiveParameter] string $token): Invitation
    {
        return $this->unexpired(Invitation::query()
            ->where('token_hash', InvitationTokenRules::hash($token))
            ->whereNull('accepted_at')
            ->firstOrFail());
    }

    public function pendingForUpdate(#[SensitiveParameter] string $token): Invitation
    {
        return $this->unexpired(Invitation::query()
            ->where('token_hash', InvitationTokenRules::hash($token))
            ->whereNull('accepted_at')
            ->lockForUpdate()
            ->firstOrFail());
    }

    private function unexpired(Invitation $invitation): Invitation
    {
        if ($invitation->isExpiredAt($this->clock->now())) {
            throw new DomainError(OrganizationError::InvitationExpired);
        }

        return $invitation;
    }
}
