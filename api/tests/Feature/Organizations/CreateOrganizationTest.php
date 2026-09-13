<?php

declare(strict_types=1);

use App\Organizations\Actions\CreateOrganization;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Membership;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;
use App\Shared\Errors\DomainError;

it('creates the organization with its creator as owner', function (): void {
    $user = User::factory()->create();

    $organization = app(CreateOrganization::class)($user, 'Équipe Été');

    expect($organization->slug)->toBe('equipe-ete')
        ->and($organization->name)->toBe('Équipe Été')
        ->and(Membership::query()->where('organization_id', $organization->id)->sole()->only(['user_id', 'role']))
        ->toBe(['user_id' => $user->id, 'role' => Role::Owner]);
});

it('gives each organization its own slug', function (): void {
    Organization::factory()->create(['slug' => 'acme']);
    $create = app(CreateOrganization::class);

    $second = $create(User::factory()->create(), 'Acme');
    $third = $create(User::factory()->create(), 'ACME!');

    expect([$second->slug, $third->slug])->toBe(['acme-2', 'acme-3']);
});

it('stops at the number of organizations a user may own', function (): void {
    $user = User::factory()->create();
    $max = config()->integer('organizations.organizations.max_owned_per_user');
    Membership::factory()->count($max)->for($user)->role(Role::Owner)->create();
    Membership::factory()->for($user)->role(Role::Admin)->create();

    expect(fn () => app(CreateOrganization::class)($user, 'One too many'))
        ->toThrow(fn (DomainError $error) => expect($error->error->code())->toBe('organization_limit_reached')
            ->and($error->parameters)->toBe(['max' => $max]));
    expect(Organization::query()->where('name', 'One too many')->exists())->toBeFalse();
});
