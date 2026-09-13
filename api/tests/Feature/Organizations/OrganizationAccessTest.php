<?php

declare(strict_types=1);

use App\Organizations\Contracts\Ability;
use App\Organizations\Contracts\AccessDecision;
use App\Organizations\Contracts\OrganizationAccess;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Membership;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;

function rolesTable(): array
{
    return [
        'monitors.view' => ['owner' => true, 'admin' => true, 'member' => true, 'viewer' => true],
        'incidents.view' => ['owner' => true, 'admin' => true, 'member' => true, 'viewer' => true],
        'status-pages.view' => ['owner' => true, 'admin' => true, 'member' => true, 'viewer' => true],
        'alert-channels.view' => ['owner' => true, 'admin' => true, 'member' => true, 'viewer' => true],
        'monitors.manage' => ['owner' => true, 'admin' => true, 'member' => true, 'viewer' => false],
        'status-pages.manage' => ['owner' => true, 'admin' => true, 'member' => true, 'viewer' => false],
        'alert-channels.manage' => ['owner' => true, 'admin' => true, 'member' => false, 'viewer' => false],
        'members.manage' => ['owner' => true, 'admin' => true, 'member' => false, 'viewer' => false],
        'organization.manage' => ['owner' => true, 'admin' => true, 'member' => false, 'viewer' => false],
        'organization.delete' => ['owner' => true, 'admin' => false, 'member' => false, 'viewer' => false],
    ];
}

dataset('roles table', function (): array {
    $cases = [];
    foreach (rolesTable() as $ability => $roles) {
        foreach ($roles as $role => $allowed) {
            $cases["{$role} {$ability}"] = [Role::from($role), Ability::from($ability), $allowed];
        }
    }

    return $cases;
});

it('follows the roles table', function (Role $role, Ability $ability, bool $allowed): void {
    $membership = Membership::factory()->role($role)->create();

    expect(app(OrganizationAccess::class)->check($membership->user_id, $membership->organization_id, $ability))
        ->toBe($allowed ? AccessDecision::Allowed : AccessDecision::Forbidden);
})->with('roles table');

it('lists every ability and every role in the roles table', function (): void {
    $roles = array_unique(array_merge(...array_map(array_keys(...), array_values(rolesTable()))));

    expect(array_keys(rolesTable()))->toEqualCanonicalizing(array_map(fn (Ability $ability): string => $ability->value, Ability::cases()))
        ->and($roles)->toEqualCanonicalizing(array_map(fn (Role $role): string => $role->value, Role::cases()));
});

it('tells a non member apart from a forbidden member', function (): void {
    $organization = Organization::factory()->create();
    $outsider = User::factory()->create();
    Membership::factory()->role(Role::Owner)->create(['user_id' => $outsider->id]);

    $access = app(OrganizationAccess::class);

    expect($access->roleOf($outsider->id, $organization->id))->toBeNull()
        ->and($access->check($outsider->id, $organization->id, Ability::MonitorsView))->toBe(AccessDecision::NotMember);
});

it('gives the role of a member', function (): void {
    $membership = Membership::factory()->role(Role::Viewer)->create();

    expect(app(OrganizationAccess::class)->roleOf($membership->user_id, $membership->organization_id))->toBe(Role::Viewer);
});
