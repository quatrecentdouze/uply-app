<?php

declare(strict_types=1);

namespace App\Organizations\Http;

use App\Organizations\Contracts\Ability;
use App\Organizations\Contracts\Role;
use App\Organizations\Settings\OrganizationsSettings;
use App\Shared\Meta\MetaSection;

final readonly class OrganizationsMetaSection implements MetaSection
{
    private const int USER_NAME_MIN_LENGTH = 1;

    public function __construct(private OrganizationsSettings $settings) {}

    public function key(): string
    {
        return 'organizations';
    }

    public function describe(): array
    {
        $limits = $this->settings->organizations;

        return [
            'roles' => array_map(fn (Role $role): array => [
                'id' => $role->value,
                'abilities' => array_values(array_map(
                    fn (Ability $ability): string => $ability->value,
                    array_filter(Ability::cases(), fn (Ability $ability): bool => $this->settings->roles->allows($role, $ability)),
                )),
            ], Role::cases()),
            'abilities' => array_map(fn (Ability $ability): string => $ability->value, Ability::cases()),
            'limits' => [
                'max_owned_organizations' => $limits->maxOwnedPerUser,
                'max_pending_invitations' => $this->settings->invitations->maxPendingPerOrganization,
                'invitation_valid_days' => $this->settings->invitations->validDays,
            ],
            'lengths' => [
                'organization_name' => ['min' => $limits->nameMinLength, 'max' => $limits->nameMaxLength],
                'slug' => ['min' => $limits->slugMinLength, 'max' => $limits->slugMaxLength],
                'user_name' => ['min' => self::USER_NAME_MIN_LENGTH, 'max' => $this->settings->userNameMaxLength],
                'password' => ['min' => $this->settings->passwords->minLength, 'max' => $this->settings->passwords->maxLength],
            ],
        ];
    }
}
