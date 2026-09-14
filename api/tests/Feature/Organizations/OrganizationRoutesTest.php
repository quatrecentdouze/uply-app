<?php

declare(strict_types=1);

use App\Monitoring\Models\Monitor;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Membership;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;

function organizationWith(User $user, Role $role, array $attributes = []): Organization
{
    $organization = Organization::factory()->create($attributes);
    Membership::factory()->for($organization)->for($user)->role($role)->create();

    return $organization;
}

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

it('lists the organizations of the user with the role in each', function (): void {
    freezeClock('2026-10-28 21:00:00');
    $owned = organizationWith($this->user, Role::Owner, ['name' => 'Owned']);
    $this->travel(1)->minutes();
    $joined = organizationWith($this->user, Role::Viewer, ['name' => 'Joined']);
    Organization::factory()->create();

    $this->actingAs($this->user)->getJson('/api/v1/organizations')
        ->assertOk()
        ->assertJsonPath('data.0.id', $owned->id)
        ->assertJsonPath('data.0.role', 'owner')
        ->assertJsonPath('data.1.id', $joined->id)
        ->assertJsonPath('data.1.role', 'viewer')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.per_page', 25);
});

it('pages the organization list', function (): void {
    organizationWith($this->user, Role::Owner);
    organizationWith($this->user, Role::Member);

    $this->actingAs($this->user)->getJson('/api/v1/organizations?per_page=1&page=2')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.last_page', 2);
    $this->getJson('/api/v1/organizations?per_page=101')->assertUnprocessable()->assertJsonValidationErrors('per_page');
});

it('creates an organization owned by its creator', function (): void {
    $this->actingAs($this->user)->postJson('/api/v1/organizations', ['name' => '  Acme Monitoring  '])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Acme Monitoring')
        ->assertJsonPath('data.slug', 'acme-monitoring')
        ->assertJsonPath('data.role', 'owner');
});

it('bounds the organization name', function (string $name): void {
    $this->actingAs($this->user)->postJson('/api/v1/organizations', ['name' => $name])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
})->with(['too short' => ['A'], 'too long' => [str_repeat('a', 81)], 'blank' => ['   ']]);

it('answers the organization limit with its code', function (): void {
    Membership::factory()->count(config()->integer('organizations.organizations.max_owned_per_user'))->for($this->user)->role(Role::Owner)->create();

    $this->actingAs($this->user)->postJson('/api/v1/organizations', ['name' => 'One too many'])
        ->assertConflict()
        ->assertJsonPath('code', 'organization_limit_reached');
});

it('keeps organizations for verified users', function (): void {
    $this->actingAs(User::factory()->unverified()->create())->postJson('/api/v1/organizations', ['name' => 'Acme'])->assertForbidden();
});

it('asks a guest to log in before listing organizations', function (): void {
    $this->getJson('/api/v1/organizations')->assertUnauthorized();
});

it('shows an organization to its members only', function (): void {
    $organization = organizationWith($this->user, Role::Member);
    $outsider = User::factory()->create();

    $this->actingAs($this->user)->getJson("/api/v1/organizations/{$organization->id}")
        ->assertOk()
        ->assertJsonPath('data.role', 'member');
    $this->actingAs($outsider)->getJson("/api/v1/organizations/{$organization->id}")->assertNotFound();
    $this->actingAs($outsider)->getJson('/api/v1/organizations/01jz0000000000000000000000')->assertNotFound();
});

it('renames an organization for the roles that manage it', function (Role $role, int $status): void {
    $organization = organizationWith($this->user, $role, ['name' => 'Before']);

    $this->actingAs($this->user)->patchJson("/api/v1/organizations/{$organization->id}", ['name' => 'After'])->assertStatus($status);

    expect($organization->refresh()->name)->toBe($status === 200 ? 'After' : 'Before');
})->with([
    'owner' => [Role::Owner, 200],
    'admin' => [Role::Admin, 200],
    'member' => [Role::Member, 403],
    'viewer' => [Role::Viewer, 403],
]);

it('keeps the slug when the organization is renamed', function (): void {
    $organization = organizationWith($this->user, Role::Owner, ['name' => 'Before', 'slug' => 'before']);

    $this->actingAs($this->user)->patchJson("/api/v1/organizations/{$organization->id}", ['name' => 'After', 'slug' => 'after'])
        ->assertOk()
        ->assertJsonPath('data.slug', 'before');
});

it('deletes an organization with everything in it for its owner', function (): void {
    $organization = organizationWith($this->user, Role::Owner);
    $monitor = Monitor::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($this->user)->deleteJson("/api/v1/organizations/{$organization->id}", ['password' => 'password'])->assertNoContent();

    expect(Organization::query()->whereKey($organization->id)->exists())->toBeFalse()
        ->and(Monitor::query()->whereKey($monitor->id)->exists())->toBeFalse()
        ->and(Membership::query()->where('organization_id', $organization->id)->exists())->toBeFalse();
});

it('keeps the organization deletion for the owner with the password', function (Role $role, array $input, int $status): void {
    $organization = organizationWith($this->user, $role);

    $this->actingAs($this->user)->deleteJson("/api/v1/organizations/{$organization->id}", $input)->assertStatus($status);

    expect(Organization::query()->whereKey($organization->id)->exists())->toBeTrue();
})->with([
    'admin' => [Role::Admin, ['password' => 'password'], 403],
    'member' => [Role::Member, ['password' => 'password'], 403],
    'owner without password' => [Role::Owner, [], 422],
    'owner with a wrong password' => [Role::Owner, ['password' => 'not the password'], 422],
]);

it('hides the organization changes from a non member', function (): void {
    $organization = Organization::factory()->create();

    $this->actingAs($this->user)->patchJson("/api/v1/organizations/{$organization->id}", ['name' => 'Taken'])->assertNotFound();
    $this->deleteJson("/api/v1/organizations/{$organization->id}", ['password' => 'password'])->assertNotFound();
});
