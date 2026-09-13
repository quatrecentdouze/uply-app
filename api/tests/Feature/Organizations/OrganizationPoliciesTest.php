<?php

declare(strict_types=1);

use App\Organizations\Contracts\Role;
use App\Organizations\Models\Membership;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;
use Illuminate\Support\Facades\Gate;

function statusFor(User $user, string $ability, mixed $arguments): int
{
    $response = Gate::forUser($user)->inspect($ability, $arguments);

    return $response->allowed() ? 200 : ($response->status() ?? 403);
}

function memberOf(Organization $organization, Role $role): User
{
    return Membership::factory()->for($organization)->role($role)->create()->user()->sole();
}

it('answers the organization abilities by role', function (Role $role, int $view, int $update, int $delete): void {
    $organization = Organization::factory()->create();
    $user = memberOf($organization, $role);

    expect(statusFor($user, 'view', $organization))->toBe($view)
        ->and(statusFor($user, 'update', $organization))->toBe($update)
        ->and(statusFor($user, 'delete', $organization))->toBe($delete);
})->with([
    'owner' => [Role::Owner, 200, 200, 200],
    'admin' => [Role::Admin, 200, 200, 403],
    'member' => [Role::Member, 200, 403, 403],
    'viewer' => [Role::Viewer, 200, 403, 403],
]);

it('hides an organization from a non member', function (): void {
    $organization = Organization::factory()->create();
    $outsider = memberOf(Organization::factory()->create(), Role::Owner);

    expect(statusFor($outsider, 'view', $organization))->toBe(404)
        ->and(statusFor($outsider, 'update', $organization))->toBe(404)
        ->and(statusFor($outsider, 'delete', $organization))->toBe(404)
        ->and(statusFor($outsider, 'viewAny', [Membership::class, $organization]))->toBe(404);
});

it('answers the member abilities by role', function (Role $role, int $list, int $change, int $remove): void {
    $organization = Organization::factory()->create();
    $user = memberOf($organization, $role);
    $other = Membership::factory()->for($organization)->role(Role::Viewer)->create();

    expect(statusFor($user, 'viewAny', [Membership::class, $organization]))->toBe($list)
        ->and(statusFor($user, 'update', $other))->toBe($change)
        ->and(statusFor($user, 'delete', $other))->toBe($remove);
})->with([
    'owner' => [Role::Owner, 200, 200, 200],
    'admin' => [Role::Admin, 200, 200, 200],
    'member' => [Role::Member, 200, 403, 403],
    'viewer' => [Role::Viewer, 200, 403, 403],
]);

it('lets any member leave', function (Role $role): void {
    $membership = Membership::factory()->role($role)->create();

    expect(statusFor($membership->user()->sole(), 'delete', $membership))->toBe(200);
})->with([Role::Owner, Role::Admin, Role::Member, Role::Viewer]);
