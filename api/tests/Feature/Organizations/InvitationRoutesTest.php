<?php

declare(strict_types=1);

use App\Organizations\Contracts\Role;
use App\Organizations\Models\Invitation;
use App\Organizations\Models\Membership;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;
use App\Organizations\Notifications\InvitationNotification;
use App\Organizations\Rules\InvitationTokenRules;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

function invitationsUrl(Organization $organization, ?Invitation $invitation = null): string
{
    return "/api/v1/organizations/{$organization->id}/invitations".($invitation === null ? '' : "/{$invitation->id}");
}

function managerOf(Organization $organization, Role $role): User
{
    return Membership::factory()->for($organization)->role($role)->create()->user()->sole();
}

function mailedInvitationToken(string $email): string
{
    $token = '';
    Notification::assertSentOnDemand(InvitationNotification::class, function (InvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($email, &$token): bool {
        if ($notifiable->routes !== ['mail' => $email]) {
            return false;
        }
        $token = basename((string) $notification->toMail($notifiable)->actionUrl);

        return true;
    });

    return $token;
}

beforeEach(function (): void {
    Notification::fake();
    freezeClock('2026-10-29 10:00:00');
    $this->organization = Organization::factory()->create();
    $this->owner = managerOf($this->organization, Role::Owner);
});

it('invites by email and mails a link whose token matches the stored hash', function (): void {
    $this->actingAs($this->owner)->postJson(invitationsUrl($this->organization), ['email' => 'Grace@Example.com', 'role' => 'admin'])
        ->assertCreated()
        ->assertJsonPath('data.email', 'grace@example.com')
        ->assertJsonPath('data.role', 'admin')
        ->assertJsonPath('data.invited_by.id', $this->owner->id)
        ->assertJsonPath('data.expires_at', '2026-11-05T10:00:00Z')
        ->assertJsonMissingPath('data.token')
        ->assertJsonMissingPath('data.token_hash');

    $token = mailedInvitationToken('grace@example.com');
    expect(Invitation::query()->where('token_hash', InvitationTokenRules::hash($token))->exists())->toBeTrue();
});

it('keeps invitations for the roles that manage members', function (Role $role, int $list, int $create, int $revoke): void {
    $actor = managerOf($this->organization, $role);
    $invitation = Invitation::factory()->for($this->organization)->create();

    $this->actingAs($actor)->getJson(invitationsUrl($this->organization))->assertStatus($list);
    $this->postJson(invitationsUrl($this->organization), ['email' => 'new@example.com', 'role' => 'viewer'])->assertStatus($create);
    $this->deleteJson(invitationsUrl($this->organization, $invitation))->assertStatus($revoke);
})->with([
    'owner' => [Role::Owner, 200, 201, 204],
    'admin' => [Role::Admin, 200, 201, 204],
    'member' => [Role::Member, 403, 403, 403],
    'viewer' => [Role::Viewer, 403, 403, 403],
]);

it('hides the invitations from a non member', function (): void {
    $outsider = managerOf(Organization::factory()->create(), Role::Owner);
    $invitation = Invitation::factory()->for($this->organization)->create();

    $this->actingAs($outsider)->getJson(invitationsUrl($this->organization))->assertNotFound();
    $this->postJson(invitationsUrl($this->organization), ['email' => 'new@example.com', 'role' => 'viewer'])->assertNotFound();
    $this->deleteJson(invitationsUrl($this->organization, $invitation))->assertNotFound();
});

it('lists the pending invitations only', function (): void {
    $pending = Invitation::factory()->for($this->organization)->create(['invited_by_user_id' => $this->owner->id]);
    $expired = Invitation::factory()->for($this->organization)->expired()->create();
    Invitation::factory()->for($this->organization)->accepted()->create();
    Invitation::factory()->create();

    $this->actingAs($this->owner)->getJson(invitationsUrl($this->organization))
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.id', $pending->id)
        ->assertJsonPath('data.0.invited_by.name', $this->owner->name)
        ->assertJsonPath('data.1.id', $expired->id);
});

it('revokes an invitation inside its organization only', function (): void {
    $elsewhere = Invitation::factory()->create();
    $mine = Invitation::factory()->for($this->organization)->create();

    $this->actingAs($this->owner)->deleteJson(invitationsUrl($this->organization, $elsewhere))->assertNotFound();
    $this->deleteJson(invitationsUrl($this->organization, $mine))->assertNoContent();

    expect(Invitation::query()->whereKey($elsewhere->id)->exists())->toBeTrue()
        ->and(Invitation::query()->whereKey($mine->id)->exists())->toBeFalse();
});

function expectInvitationRefused(User $actor, Organization $organization, array $input, int $status, string $code): void
{
    test()->actingAs($actor)->postJson(invitationsUrl($organization), $input)
        ->assertStatus($status)
        ->assertJsonPath('code', $code);
    Notification::assertNothingSent();
}

it('refuses an owner invitation from an admin', function (): void {
    expectInvitationRefused(managerOf($this->organization, Role::Admin), $this->organization, ['email' => 'new@example.com', 'role' => 'owner'], 403, 'role_not_allowed');
});

it('refuses to invite a member', function (): void {
    Membership::factory()->for($this->organization)->for(User::factory()->create(['email' => 'member@example.com']))->create();

    expectInvitationRefused($this->owner, $this->organization, ['email' => 'Member@example.com', 'role' => 'viewer'], 409, 'already_member');
});

it('refuses a second pending invitation', function (): void {
    Invitation::factory()->for($this->organization)->create(['email' => 'pending@example.com']);

    expectInvitationRefused($this->owner, $this->organization, ['email' => 'PENDING@example.com', 'role' => 'viewer'], 409, 'invitation_pending');
});

it('refuses an invitation over the pending limit', function (): void {
    Invitation::factory()->count(config()->integer('organizations.invitations.max_pending_per_organization'))->for($this->organization)->create();

    expectInvitationRefused($this->owner, $this->organization, ['email' => 'new@example.com', 'role' => 'viewer'], 409, 'invitation_limit_reached');
});

it('validates the invitation input', function (array $input, string $field): void {
    $this->actingAs($this->owner)->postJson(invitationsUrl($this->organization), $input)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'missing email' => [['role' => 'viewer'], 'email'],
    'not an email' => [['email' => 'not-an-email', 'role' => 'viewer'], 'email'],
    'unknown role' => [['email' => 'new@example.com', 'role' => 'superuser'], 'role'],
]);
