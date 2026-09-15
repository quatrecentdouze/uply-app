<?php

declare(strict_types=1);

use App\Organizations\Actions\InviteMember;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Invitation;
use App\Organizations\Models\Membership;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;
use App\Organizations\Rules\InvitationTokenRules;
use App\Shared\Errors\DomainError;

function inviteBy(Role $role, Organization $organization): User
{
    return Membership::factory()->for($organization)->role($role)->create()->user()->sole();
}

function expectInvitationError(Closure $invite, string $code): void
{
    expect($invite)->toThrow(fn (DomainError $error) => expect($error->error->code())->toBe($code));
}

beforeEach(function (): void {
    freezeClock('2026-10-29 10:00:00');
    $this->organization = Organization::factory()->create();
    $this->owner = inviteBy(Role::Owner, $this->organization);
});

it('issues an invitation that expires after the configured days', function (): void {
    $issued = app(InviteMember::class)($this->owner, $this->organization, 'ada@example.com', Role::Member);

    $invitation = $issued->invitation->refresh();
    expect(strlen($issued->token))->toBe(config()->integer('organizations.invitations.token_length'))
        ->and($invitation->token_hash)->toBe(InvitationTokenRules::hash($issued->token))
        ->and($invitation->role)->toBe(Role::Member)
        ->and($invitation->invited_by_user_id)->toBe($this->owner->id)
        ->and($invitation->expiresAt()->toIso8601ZuluString())->toBe('2026-11-05T10:00:00Z')
        ->and($invitation->accepted_at)->toBeNull();
});

it('lets an admin invite any role but owner', function (Role $role): void {
    $admin = inviteBy(Role::Admin, $this->organization);

    expect(app(InviteMember::class)($admin, $this->organization, 'ada@example.com', $role)->invitation->role)->toBe($role);
})->with([Role::Admin, Role::Member, Role::Viewer]);

it('keeps the owner invitations for owners', function (): void {
    $admin = inviteBy(Role::Admin, $this->organization);

    expectInvitationError(fn () => app(InviteMember::class)($admin, $this->organization, 'ada@example.com', Role::Owner), 'role_not_allowed');
    expect(app(InviteMember::class)($this->owner, $this->organization, 'grace@example.com', Role::Owner)->invitation->role)->toBe(Role::Owner);
});

it('refuses to invite a member', function (): void {
    $member = inviteBy(Role::Member, $this->organization);

    expectInvitationError(fn () => app(InviteMember::class)($this->owner, $this->organization, strtoupper($member->email), Role::Viewer), 'already_member');
});

it('refuses a second pending invitation for the same email', function (): void {
    Invitation::factory()->for($this->organization)->create(['email' => 'ada@example.com']);

    expectInvitationError(fn () => app(InviteMember::class)($this->owner, $this->organization, 'ADA@example.com', Role::Member), 'invitation_pending');
});

it('replaces an expired invitation for the same email', function (): void {
    $expired = Invitation::factory()->for($this->organization)->expired()->create(['email' => 'ada@example.com']);

    $issued = app(InviteMember::class)($this->owner, $this->organization, 'ada@example.com', Role::Member);

    expect(Invitation::query()->whereKey($expired->id)->exists())->toBeFalse()
        ->and($issued->invitation->exists)->toBeTrue();
});

it('stops at the pending invitations limit, without counting the expired ones', function (): void {
    $max = config()->integer('organizations.invitations.max_pending_per_organization');
    Invitation::factory()->count($max - 1)->for($this->organization)->create();
    Invitation::factory()->count(3)->for($this->organization)->expired()->create();
    Invitation::factory()->count(2)->for($this->organization)->accepted()->create();

    app(InviteMember::class)($this->owner, $this->organization, 'last@example.com', Role::Member);

    expect(fn () => app(InviteMember::class)($this->owner, $this->organization, 'one-more@example.com', Role::Member))
        ->toThrow(fn (DomainError $error) => expect($error->error->code())->toBe('invitation_limit_reached')
            ->and($error->parameters)->toBe(['max' => $max]));
});
