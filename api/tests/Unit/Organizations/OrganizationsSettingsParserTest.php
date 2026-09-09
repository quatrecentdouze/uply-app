<?php

declare(strict_types=1);

use App\Organizations\Contracts\Ability;
use App\Organizations\Contracts\Role;
use App\Organizations\Settings\OrganizationsSettingsParser;
use App\Shared\Settings\InvalidSettingException;

function shippedOrganizationsConfig(): array
{
    return require dirname(__DIR__, 3).'/config/organizations.php';
}

it('parses the shipped organizations file', function (): void {
    $settings = (new OrganizationsSettingsParser)->parse('config/organizations.php', shippedOrganizationsConfig());

    expect($settings->frontendUrl)->toBe('http://localhost:5173')
        ->and($settings->frontendLink('/invitations/abc'))->toBe('http://localhost:5173/invitations/abc')
        ->and($settings->userNameMaxLength)->toBe(80)
        ->and($settings->passwords->minLength)->toBe(12)
        ->and($settings->passwords->maxLength)->toBe(255)
        ->and($settings->organizations->maxOwnedPerUser)->toBe(10)
        ->and($settings->organizations->nameMinLength)->toBe(2)
        ->and($settings->organizations->nameMaxLength)->toBe(80)
        ->and($settings->organizations->slugMinLength)->toBe(3)
        ->and($settings->organizations->slugMaxLength)->toBe(48)
        ->and($settings->invitations->validDays)->toBe(7)
        ->and($settings->invitations->maxPendingPerOrganization)->toBe(50)
        ->and($settings->invitations->tokenLength)->toBe(40)
        ->and($settings->rateLimits->loginPerMinute)->toBe(5)
        ->and($settings->rateLimits->twoFactorPerMinute)->toBe(5);
});

it('maps the roles table of the contract', function (Role $role, array $granted): void {
    $roles = (new OrganizationsSettingsParser)->parse('config/organizations.php', shippedOrganizationsConfig())->roles;

    foreach (Ability::cases() as $ability) {
        expect($roles->allows($role, $ability))->toBe(in_array($ability->value, $granted, true), "{$role->value} {$ability->value}");
    }
})->with([
    'owner' => [Role::Owner, array_map(fn (Ability $ability): string => $ability->value, Ability::cases())],
    'admin' => [Role::Admin, ['monitors.view', 'incidents.view', 'status-pages.view', 'alert-channels.view', 'monitors.manage', 'status-pages.manage', 'alert-channels.manage', 'members.manage', 'organization.manage']],
    'member' => [Role::Member, ['monitors.view', 'incidents.view', 'status-pages.view', 'alert-channels.view', 'monitors.manage', 'status-pages.manage']],
    'viewer' => [Role::Viewer, ['monitors.view', 'incidents.view', 'status-pages.view', 'alert-channels.view']],
]);

it('rejects an invalid organizations setting with its file and key', function (string $key, mixed $value): void {
    $config = shippedOrganizationsConfig();
    data_set($config, $key, $value);

    try {
        (new OrganizationsSettingsParser)->parse('config/organizations.php', $config);
        $this->fail("{$key} was accepted");
    } catch (InvalidSettingException $exception) {
        expect($exception->source)->toBe('config/organizations.php')
            ->and($exception->key)->toBe($key);
    }
})->with([
    'frontend not a url' => ['frontend_url', 'localhost:5173'],
    'user name zero' => ['users.name_max_length', 0],
    'password min zero' => ['passwords.min_length', 0],
    'password max below min' => ['passwords.max_length', 8],
    'owned zero' => ['organizations.max_owned_per_user', 0],
    'name min zero' => ['organizations.name_min_length', 0],
    'name max below min' => ['organizations.name_max_length', 1],
    'slug min zero' => ['organizations.slug_min_length', 0],
    'slug max below min' => ['organizations.slug_max_length', 2],
    'invitation days zero' => ['invitations.valid_days', 0],
    'pending zero' => ['invitations.max_pending_per_organization', 0],
    'short token' => ['invitations.token_length', 16],
    'login limit zero' => ['rate_limits.login_per_minute', 0],
    'two factor limit zero' => ['rate_limits.two_factor_per_minute', 0],
    'unknown ability' => ['roles.viewer', ['monitors.view', 'billing.view']],
    'owner missing an ability' => ['roles.owner', ['monitors.view']],
    'role missing' => ['roles.member', null],
]);
