<?php

declare(strict_types=1);

use App\Organizations\Contracts\Role;
use App\Organizations\Models\Membership;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;

function seat(Organization $organization, Role $role, array $user = []): Membership
{
    return Membership::factory()->for($organization)->for(User::factory()->create($user))->role($role)->create();
}

function actorOf(Membership $membership): User
{
    return $membership->user()->sole();
}

function membersUrl(Organization $organization, ?Membership $membership = null): string
{
    return "/api/v1/organizations/{$organization->id}/members".($membership === null ? '' : "/{$membership->id}");
}

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->owner = seat($this->organization, Role::Owner);
});

it('lists the members to any member', function (Role $role): void {
    $viewer = seat($this->organization, $role, ['name' => 'Grace Hopper', 'email' => 'grace@example.com']);

    $this->actingAs(actorOf($viewer))->getJson(membersUrl($this->organization))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.1.id', $viewer->id)
        ->assertJsonPath('data.1.role', $role->value)
        ->assertJsonPath('data.1.user.name', 'Grace Hopper')
        ->assertJsonPath('data.1.user.email', 'grace@example.com')
        ->assertJsonPath('meta.total', 2);
})->with([Role::Admin, Role::Member, Role::Viewer]);

it('hides the members from a non member', function (): void {
    $outsider = seat(Organization::factory()->create(), Role::Owner);

    $this->actingAs(actorOf($outsider))->getJson(membersUrl($this->organization))->assertNotFound();
    $this->patchJson(membersUrl($this->organization, $this->owner), ['role' => 'viewer'])->assertNotFound();
    $this->deleteJson(membersUrl($this->organization, $this->owner))->assertNotFound();
});

it('changes roles for the roles that manage members', function (Role $actorRole, Role $from, Role $to, int $status): void {
    $actor = seat($this->organization, $actorRole);
    $target = seat($this->organization, $from);

    $this->actingAs(actorOf($actor))->patchJson(membersUrl($this->organization, $target), ['role' => $to->value])->assertStatus($status);

    expect($target->refresh()->role)->toBe($status === 200 ? $to : $from);
})->with([
    'owner promotes a member to admin' => [Role::Owner, Role::Member, Role::Admin, 200],
    'owner makes another owner' => [Role::Owner, Role::Admin, Role::Owner, 200],
    'admin promotes a viewer to member' => [Role::Admin, Role::Viewer, Role::Member, 200],
    'admin demotes an admin' => [Role::Admin, Role::Admin, Role::Viewer, 200],
    'member cannot change roles' => [Role::Member, Role::Viewer, Role::Member, 403],
    'viewer cannot change roles' => [Role::Viewer, Role::Member, Role::Viewer, 403],
]);

it('never lets an admin grant owner or touch an owner', function (Role $from, Role $to): void {
    $admin = seat($this->organization, Role::Admin);
    $target = $from === Role::Owner ? seat($this->organization, Role::Owner) : seat($this->organization, $from);

    $this->actingAs(actorOf($admin))->patchJson(membersUrl($this->organization, $target), ['role' => $to->value])
        ->assertForbidden()
        ->assertJsonPath('code', 'role_not_allowed');

    expect($target->refresh()->role)->toBe($from);
})->with([
    'grant owner' => [Role::Member, Role::Owner],
    'demote an owner' => [Role::Owner, Role::Admin],
]);

it('keeps the last owner', function (): void {
    $this->actingAs(actorOf($this->owner))->patchJson(membersUrl($this->organization, $this->owner), ['role' => 'admin'])
        ->assertConflict()
        ->assertJsonPath('code', 'last_owner');
    $this->deleteJson(membersUrl($this->organization, $this->owner))
        ->assertConflict()
        ->assertJsonPath('code', 'last_owner');

    expect($this->owner->refresh()->role)->toBe(Role::Owner);
});

it('lets an owner step down once another owner exists', function (): void {
    seat($this->organization, Role::Owner);

    $this->actingAs(actorOf($this->owner))->patchJson(membersUrl($this->organization, $this->owner), ['role' => 'member'])
        ->assertOk()
        ->assertJsonPath('data.role', 'member');
});

it('removes members for the roles that manage them', function (Role $actorRole, Role $targetRole, int $status): void {
    $actor = seat($this->organization, $actorRole);
    $target = seat($this->organization, $targetRole);

    $this->actingAs(actorOf($actor))->deleteJson(membersUrl($this->organization, $target))->assertStatus($status);

    expect(Membership::query()->whereKey($target->id)->exists())->toBe($status !== 204);
})->with([
    'owner removes an owner' => [Role::Owner, Role::Owner, 204],
    'admin removes a member' => [Role::Admin, Role::Member, 204],
    'admin cannot remove an owner' => [Role::Admin, Role::Owner, 403],
    'member cannot remove' => [Role::Member, Role::Viewer, 403],
    'viewer cannot remove' => [Role::Viewer, Role::Member, 403],
]);

it('lets any member leave', function (Role $role): void {
    $leaving = seat($this->organization, $role);

    $this->actingAs(actorOf($leaving))->deleteJson(membersUrl($this->organization, $leaving))->assertNoContent();

    expect(Membership::query()->whereKey($leaving->id)->exists())->toBeFalse();
})->with([Role::Admin, Role::Member, Role::Viewer]);

it('finds a membership only inside its organization', function (): void {
    $elsewhere = seat(Organization::factory()->create(), Role::Member);

    $this->actingAs(actorOf($this->owner))->patchJson(membersUrl($this->organization, $elsewhere), ['role' => 'viewer'])->assertNotFound();
    $this->deleteJson(membersUrl($this->organization, $elsewhere))->assertNotFound();
});

it('refuses an unknown role', function (): void {
    $target = seat($this->organization, Role::Member);

    $this->actingAs(actorOf($this->owner))->patchJson(membersUrl($this->organization, $target), ['role' => 'superuser'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');
});
