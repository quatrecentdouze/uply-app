<?php

declare(strict_types=1);

namespace App\Organizations\Contracts;

interface OrganizationAccess
{
    public function roleOf(string $userId, string $organizationId): ?Role;

    public function check(string $userId, string $organizationId, Ability $ability): AccessDecision;
}
