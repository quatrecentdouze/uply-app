<?php

declare(strict_types=1);

use App\Organizations\Contracts\Role;
use App\Organizations\Models\Invitation;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;
use App\Organizations\Rules\InvitationTokenRules;
use Carbon\CarbonImmutable;

it('stores only the hash of the token', function (): void {
    $invitation = Invitation::factory()->withToken('plain-token')->create();

    expect($invitation->refresh()->token_hash)->toBe(hash('sha256', 'plain-token'))
        ->and(InvitationTokenRules::hash('plain-token'))->toHaveLength(64)
        ->and($invitation->toArray())->not->toHaveKey('token_hash');
});

it('reads the role and the times as values', function (): void {
    $invitation = Invitation::factory()->role(Role::Admin)->create(['expires_at' => '2026-11-05 10:00:00']);

    $invitation->refresh();
    expect($invitation->role)->toBe(Role::Admin)
        ->and($invitation->expiresAt()->toIso8601ZuluString())->toBe('2026-11-05T10:00:00Z')
        ->and($invitation->isExpiredAt(CarbonImmutable::parse('2026-11-05 09:59:59')))->toBeFalse()
        ->and($invitation->isExpiredAt(CarbonImmutable::parse('2026-11-05 10:00:00')))->toBeTrue();
});

it('links the organization and the inviter', function (): void {
    $organization = Organization::factory()->create();
    $inviter = User::factory()->create();
    $invitation = Invitation::factory()->for($organization)->create(['invited_by_user_id' => $inviter->id]);

    expect($invitation->organization()->sole()->id)->toBe($organization->id)
        ->and($invitation->inviter()->sole()->id)->toBe($inviter->id)
        ->and($organization->invitations()->sole()->id)->toBe($invitation->id);
});
