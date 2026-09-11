<?php

declare(strict_types=1);

namespace App\Organizations\Enums;

use App\Shared\Errors\ErrorCode;

enum OrganizationError: string implements ErrorCode
{
    case SoleOwner = 'sole_owner';
    case LastOwner = 'last_owner';
    case OrganizationLimitReached = 'organization_limit_reached';
    case RoleNotAllowed = 'role_not_allowed';
    case InvitationEmailMismatch = 'invitation_email_mismatch';
    case InvitationExpired = 'invitation_expired';
    case InvitationPending = 'invitation_pending';
    case AlreadyMember = 'already_member';
    case InvitationLimitReached = 'invitation_limit_reached';

    public function code(): string
    {
        return $this->value;
    }

    public function httpStatus(): int
    {
        return match ($this) {
            self::RoleNotAllowed, self::InvitationEmailMismatch => 403,
            self::InvitationExpired => 410,
            default => 409,
        };
    }

    public function messageKey(): string
    {
        return 'organizations.errors.'.$this->value;
    }
}
