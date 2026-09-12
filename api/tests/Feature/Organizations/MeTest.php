<?php

declare(strict_types=1);

use App\Organizations\Contracts\Role;
use App\Organizations\Models\Membership;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;
use Tests\Support\TwoFactorUser;

it('asks a guest to log in', function (): void {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

it('describes the user and the memberships', function (): void {
    freezeClock('2026-10-28 21:47:21');
    $user = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
    $organization = Organization::factory()->create(['name' => 'Analytical Engines', 'slug' => 'analytical-engines']);
    $membership = Membership::factory()->for($organization)->for($user)->role(Role::Admin)->create();

    $this->actingAs($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $user->id,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'email_verified_at' => '2026-10-28T21:47:21Z',
            'two_factor_enabled' => false,
            'created_at' => '2026-10-28T21:47:21Z',
            'memberships' => [[
                'id' => $membership->id,
                'role' => 'admin',
                'organization' => ['id' => $organization->id, 'name' => 'Analytical Engines', 'slug' => 'analytical-engines'],
                'joined_at' => '2026-10-28T21:47:21Z',
            ]],
        ]]);
});

it('answers an unverified user with an empty membership list', function (): void {
    $this->actingAs(User::factory()->unverified()->create())->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.email_verified_at', null)
        ->assertJsonPath('data.memberships', []);
});

it('says two factor is on only once it is confirmed', function (): void {
    $confirmed = TwoFactorUser::create()->user;
    $pending = TwoFactorUser::create()->user;
    $pending->forceFill(['two_factor_confirmed_at' => null])->save();

    $this->actingAs($confirmed)->getJson('/api/v1/me')->assertJsonPath('data.two_factor_enabled', true);
    $this->actingAs($pending)->getJson('/api/v1/me')->assertJsonPath('data.two_factor_enabled', false);
});

it('never shows a password or a two factor secret', function (): void {
    $response = $this->actingAs(TwoFactorUser::create()->user)->getJson('/api/v1/me')->assertOk();

    expect(array_keys((array) $response->json('data')))->not->toContain('password')
        ->not->toContain('two_factor_secret')
        ->not->toContain('two_factor_recovery_codes')
        ->not->toContain('remember_token');
});
