<?php

declare(strict_types=1);

use App\Organizations\Contracts\Role;
use App\Organizations\Models\Invitation;
use App\Organizations\Models\Membership;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;
use App\Organizations\Notifications\InvitationNotification;
use App\Organizations\Rules\InvitationTokenRules;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->clock = freezeClock('2026-10-29 10:00:00');
    $this->organization = Organization::factory()->create(['name' => 'Analytical Engines']);
    $this->inviter = User::factory()->create(['name' => 'Ada Lovelace']);
    Membership::factory()->for($this->organization)->for($this->inviter)->role(Role::Owner)->create();
    $this->invitation = Invitation::factory()
        ->for($this->organization)
        ->role(Role::Member)
        ->withToken('validInvitationToken')
        ->create(['email' => 'grace@example.com', 'invited_by_user_id' => $this->inviter->id, 'expires_at' => '2026-11-05 10:00:00']);
});

it('shows a pending invitation to anyone holding the token', function (): void {
    $this->getJson('/api/v1/invitations/validInvitationToken')
        ->assertOk()
        ->assertExactJson(['data' => [
            'organization' => ['name' => 'Analytical Engines'],
            'role' => 'member',
            'invited_by' => ['name' => 'Ada Lovelace'],
            'email' => 'grace@example.com',
            'expires_at' => '2026-11-05T10:00:00Z',
        ]]);
});

it('shows the invitation without an inviter once the inviter is gone', function (): void {
    $this->inviter->delete();

    $this->getJson('/api/v1/invitations/validInvitationToken')
        ->assertOk()
        ->assertJsonPath('data.invited_by', null);
});

it('answers an unknown, accepted or expired token', function (): void {
    $this->getJson('/api/v1/invitations/unknownToken')->assertNotFound();

    $this->clock->moveTo(CarbonImmutable::parse('2026-11-05 10:00:00'));
    $this->getJson('/api/v1/invitations/validInvitationToken')
        ->assertStatus(410)
        ->assertJsonPath('code', 'invitation_expired');

    $this->invitation->update(['accepted_at' => now()]);
    $this->getJson('/api/v1/invitations/validInvitationToken')->assertNotFound();
});

it('joins the organization with the invited role', function (): void {
    $grace = User::factory()->create(['email' => 'Grace@Example.com']);

    $this->actingAs($grace)->postJson('/api/v1/invitations/validInvitationToken/accept')
        ->assertCreated()
        ->assertJsonPath('data.id', $this->organization->id)
        ->assertJsonPath('data.role', 'member');

    expect(Membership::query()->where('organization_id', $this->organization->id)->where('user_id', $grace->id)->sole()->role)->toBe(Role::Member)
        ->and($this->invitation->refresh()->accepted_at?->toIso8601ZuluString())->toBe('2026-10-29T10:00:00Z');
    $this->postJson('/api/v1/invitations/validInvitationToken/accept')->assertNotFound();
});

it('refuses an acceptance from another email', function (): void {
    $this->actingAs(User::factory()->create(['email' => 'someone@example.com']))
        ->postJson('/api/v1/invitations/validInvitationToken/accept')
        ->assertForbidden()
        ->assertJsonPath('code', 'invitation_email_mismatch');

    expect($this->invitation->refresh()->accepted_at)->toBeNull();
});

it('refuses an expired invitation', function (): void {
    $grace = User::factory()->create(['email' => 'grace@example.com']);
    $this->clock->moveTo(CarbonImmutable::parse('2026-11-05 10:00:01'));

    $this->actingAs($grace)->postJson('/api/v1/invitations/validInvitationToken/accept')
        ->assertStatus(410)
        ->assertJsonPath('code', 'invitation_expired');
});

it('refuses an invitation for an existing member', function (): void {
    $grace = User::factory()->create(['email' => 'grace@example.com']);
    Membership::factory()->for($this->organization)->for($grace)->role(Role::Viewer)->create();

    $this->actingAs($grace)->postJson('/api/v1/invitations/validInvitationToken/accept')
        ->assertConflict()
        ->assertJsonPath('code', 'already_member');
});

it('keeps the acceptance for verified and logged in users', function (): void {
    $this->postJson('/api/v1/invitations/validInvitationToken/accept')->assertUnauthorized();
    $this->actingAs(User::factory()->unverified()->create(['email' => 'grace@example.com']))
        ->postJson('/api/v1/invitations/validInvitationToken/accept')
        ->assertForbidden();
});

it('takes an invitation from the mail to a new membership', function (): void {
    Notification::fake();
    $newcomer = User::factory()->create(['email' => 'newcomer@example.com']);

    $this->actingAs($this->inviter)->postJson("/api/v1/organizations/{$this->organization->id}/invitations", ['email' => 'newcomer@example.com', 'role' => 'viewer'])->assertCreated();
    $token = '';
    Notification::assertSentOnDemand(InvitationNotification::class, function (InvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable) use (&$token): bool {
        $token = basename((string) $notification->toMail($notifiable)->actionUrl);

        return true;
    });

    expect(Invitation::query()->where('token_hash', InvitationTokenRules::hash($token))->exists())->toBeTrue();
    $this->actingAs($newcomer)->postJson("/api/v1/invitations/{$token}/accept")->assertCreated()->assertJsonPath('data.role', 'viewer');
});
