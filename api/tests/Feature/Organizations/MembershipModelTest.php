<?php

declare(strict_types=1);

use App\Organizations\Contracts\Role;
use App\Organizations\Models\Membership;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;

it('reads the role as its enum', function (): void {
    $membership = Membership::factory()->role(Role::Admin)->create();

    expect($membership->refresh()->role)->toBe(Role::Admin);
});

it('links users and organizations through memberships', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    Membership::factory()->for($organization)->for($owner)->role(Role::Owner)->create();
    Membership::factory()->for($organization)->for($viewer)->role(Role::Viewer)->create();

    expect($organization->memberships()->pluck('user_id')->sort()->values()->all())->toBe(collect([$owner->id, $viewer->id])->sort()->values()->all())
        ->and($owner->memberships()->sole()->organization_id)->toBe($organization->id)
        ->and($viewer->memberships()->sole()->role)->toBe(Role::Viewer);
});
