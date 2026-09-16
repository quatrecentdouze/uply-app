<?php

declare(strict_types=1);

use App\Monitoring\Models\Monitor;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Invitation;
use App\Organizations\Models\Membership;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;
use Illuminate\Support\Facades\Auth;

function joinOrganization(User $user, Organization $organization, Role $role): void
{
    Membership::factory()->for($organization)->for($user)->role($role)->create();
}

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

it('needs the password to delete the account', function (array $input): void {
    $this->actingAs($this->user)->deleteJson('/api/v1/me', $input)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    expect(User::query()->whereKey($this->user->id)->exists())->toBeTrue();
})->with(['missing' => [[]], 'wrong' => [['password' => 'not the password']]]);

it('deletes the account with the organizations where the user is alone', function (): void {
    $alone = Organization::factory()->create();
    $shared = Organization::factory()->create();
    $otherOwner = User::factory()->create();
    joinOrganization($this->user, $alone, Role::Owner);
    joinOrganization($otherOwner, $shared, Role::Owner);
    joinOrganization($this->user, $shared, Role::Member);
    $monitor = Monitor::factory()->create(['organization_id' => $alone->id]);

    $this->actingAs($this->user)->deleteJson('/api/v1/me', ['password' => 'password'])->assertNoContent();

    expect(User::query()->whereKey($this->user->id)->exists())->toBeFalse()
        ->and(Organization::query()->whereKey($alone->id)->exists())->toBeFalse()
        ->and(Monitor::query()->whereKey($monitor->id)->exists())->toBeFalse()
        ->and(Organization::query()->whereKey($shared->id)->exists())->toBeTrue()
        ->and(Membership::query()->where('organization_id', $shared->id)->pluck('user_id')->all())->toBe([$otherOwner->id]);
});

it('ends the session of the deleted account', function (): void {
    $this->withHeader('Origin', rtrim(config()->string('organizations.frontend_url'), '/'));
    $this->postJson('/api/v1/auth/login', ['email' => $this->user->email, 'password' => 'password'])->assertOk();
    $loginKey = Auth::guard('web')->getName();
    expect(session()->has($loginKey))->toBeTrue();

    $this->deleteJson('/api/v1/me', ['password' => 'password'])->assertNoContent();

    expect(session()->has($loginKey))->toBeFalse();
});

it('refuses while the user is the only owner of an organization with other members', function (): void {
    $organization = Organization::factory()->create();
    joinOrganization($this->user, $organization, Role::Owner);
    joinOrganization(User::factory()->create(), $organization, Role::Admin);

    $this->actingAs($this->user)->deleteJson('/api/v1/me', ['password' => 'password'])
        ->assertConflict()
        ->assertJsonPath('code', 'sole_owner');

    expect(User::query()->whereKey($this->user->id)->exists())->toBeTrue()
        ->and(Organization::query()->whereKey($organization->id)->exists())->toBeTrue();
});

it('lets a co owner leave an organization by deleting the account', function (): void {
    $organization = Organization::factory()->create();
    $coOwner = User::factory()->create();
    joinOrganization($this->user, $organization, Role::Owner);
    joinOrganization($coOwner, $organization, Role::Owner);

    $this->actingAs($this->user)->deleteJson('/api/v1/me', ['password' => 'password'])->assertNoContent();

    expect(Membership::query()->where('organization_id', $organization->id)->pluck('user_id')->all())->toBe([$coOwner->id]);
});

it('keeps the invitations sent by a deleted account without their inviter', function (): void {
    $organization = Organization::factory()->create();
    joinOrganization($this->user, $organization, Role::Admin);
    joinOrganization(User::factory()->create(), $organization, Role::Owner);
    $invitation = Invitation::factory()->for($organization)->create(['invited_by_user_id' => $this->user->id]);

    $this->actingAs($this->user)->deleteJson('/api/v1/me', ['password' => 'password'])->assertNoContent();

    expect($invitation->refresh()->invited_by_user_id)->toBeNull();
});

it('lets an unverified user delete the account', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->deleteJson('/api/v1/me', ['password' => 'password'])->assertNoContent();

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse();
});
