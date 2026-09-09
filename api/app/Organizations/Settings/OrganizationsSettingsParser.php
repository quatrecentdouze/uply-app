<?php

declare(strict_types=1);

namespace App\Organizations\Settings;

use App\Organizations\Contracts\Ability;
use App\Organizations\Contracts\Role;
use App\Shared\Settings\SettingsReader;

final class OrganizationsSettingsParser
{
    private const array FRONTEND_SCHEMES = ['http', 'https'];

    private const int MIN_TOKEN_LENGTH = 32;

    public function parse(string $source, array $values): OrganizationsSettings
    {
        $reader = new SettingsReader($source, $values);

        return new OrganizationsSettings(
            frontendUrl: $this->frontendUrl($reader),
            userNameMaxLength: $reader->integer('users.name_max_length', 1),
            passwords: $this->passwords($reader),
            organizations: $this->organizations($reader),
            invitations: new InvitationSettings(
                validDays: $reader->integer('invitations.valid_days', 1),
                maxPendingPerOrganization: $reader->integer('invitations.max_pending_per_organization', 1),
                tokenLength: $reader->integer('invitations.token_length', self::MIN_TOKEN_LENGTH),
            ),
            rateLimits: new RateLimitSettings(
                loginPerMinute: $reader->integer('rate_limits.login_per_minute', 1),
                twoFactorPerMinute: $reader->integer('rate_limits.two_factor_per_minute', 1),
            ),
            roles: $this->roles($reader),
        );
    }

    private function frontendUrl(SettingsReader $reader): string
    {
        $url = $reader->text('frontend_url');
        if (filter_var($url, FILTER_VALIDATE_URL) === false || ! in_array(parse_url($url, PHP_URL_SCHEME), self::FRONTEND_SCHEMES, true)) {
            throw $reader->invalid('frontend_url', 'must be an http or https url');
        }

        return $url;
    }

    private function passwords(SettingsReader $reader): PasswordSettings
    {
        $minimum = $reader->integer('passwords.min_length', 1);

        return new PasswordSettings($minimum, $reader->integer('passwords.max_length', $minimum));
    }

    private function organizations(SettingsReader $reader): OrganizationLimits
    {
        $nameMinimum = $reader->integer('organizations.name_min_length', 1);
        $slugMinimum = $reader->integer('organizations.slug_min_length', 1);

        return new OrganizationLimits(
            maxOwnedPerUser: $reader->integer('organizations.max_owned_per_user', 1),
            nameMinLength: $nameMinimum,
            nameMaxLength: $reader->integer('organizations.name_max_length', $nameMinimum),
            slugMinLength: $slugMinimum,
            slugMaxLength: $reader->integer('organizations.slug_max_length', $slugMinimum),
        );
    }

    private function roles(SettingsReader $reader): RoleRegistry
    {
        $abilitiesByRole = [];
        foreach (Role::cases() as $role) {
            $key = "roles.{$role->value}";
            $abilities = [];
            foreach ($reader->textList($key) as $name) {
                $ability = is_string($name) ? Ability::tryFrom($name) : null;
                if ($ability === null) {
                    throw $reader->invalid($key, 'must only hold known abilities');
                }
                $abilities[] = $ability;
            }
            $abilitiesByRole[$role->value] = $abilities;
        }
        if (count($abilitiesByRole[Role::Owner->value]) !== count(Ability::cases())) {
            throw $reader->invalid('roles.owner', 'must hold every ability');
        }

        return new RoleRegistry($abilitiesByRole);
    }
}
