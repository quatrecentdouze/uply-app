<?php

declare(strict_types=1);

use App\Organizations\Contracts\Role;
use App\Organizations\Models\Membership;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Testing\TestResponse;

function useSigningBroadcaster(): void
{
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);
    app(BroadcastManager::class)->purge();
    require base_path('routes/channels.php');
}

function authorizeChannel(string $channel): TestResponse
{
    return test()->postJson('/api/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-'.$channel]);
}

beforeEach(function (): void {
    useSigningBroadcaster();
    $this->organization = Organization::factory()->create();
});

it('signs the organization channel for any member', function (Role $role): void {
    $member = Membership::factory()->for($this->organization)->role($role)->create()->user()->sole();

    $this->actingAs($member);
    authorizeChannel("organization.{$this->organization->id}")
        ->assertOk()
        ->assertJsonPath('auth', fn (string $auth): bool => str_starts_with($auth, 'test-key:'));
})->with([Role::Owner, Role::Admin, Role::Member, Role::Viewer]);

it('refuses the channel of an organization the user is not in', function (): void {
    $outsider = Membership::factory()->role(Role::Owner)->create()->user()->sole();

    $this->actingAs($outsider);
    authorizeChannel("organization.{$this->organization->id}")->assertForbidden();
});

it('keeps the channels for verified and logged in users', function (): void {
    authorizeChannel("organization.{$this->organization->id}")->assertUnauthorized();

    $unverified = User::factory()->unverified()->create();
    Membership::factory()->for($this->organization)->for($unverified)->role(Role::Owner)->create();
    $this->actingAs($unverified);
    authorizeChannel("organization.{$this->organization->id}")->assertForbidden();
});

it('refuses any other private channel', function (): void {
    $this->actingAs(Membership::factory()->for($this->organization)->role(Role::Owner)->create()->user()->sole());

    authorizeChannel('App.Models.User.1')->assertForbidden();
});
