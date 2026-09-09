<?php

declare(strict_types=1);

namespace App\Organizations\Settings;

final readonly class OrganizationsSettings
{
    public function __construct(
        public string $frontendUrl,
        public int $userNameMaxLength,
        public PasswordSettings $passwords,
        public OrganizationLimits $organizations,
        public InvitationSettings $invitations,
        public RateLimitSettings $rateLimits,
        public RoleRegistry $roles,
    ) {}

    public function frontendLink(string $path): string
    {
        return rtrim($this->frontendUrl, '/').'/'.ltrim($path, '/');
    }
}
