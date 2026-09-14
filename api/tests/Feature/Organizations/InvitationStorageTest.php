<?php

declare(strict_types=1);

use App\Organizations\Models\Organization;
use App\Organizations\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function insertInvitation(Organization $organization, string $email, array $overrides = []): string
{
    $id = Str::lower((string) Str::ulid());
    DB::table('invitations')->insert(array_merge([
        'id' => $id,
        'organization_id' => $organization->id,
        'email' => $email,
        'role' => 'member',
        'token_hash' => hash('sha256', Str::random(40)),
        'invited_by_user_id' => null,
        'expires_at' => now()->addDays(7),
        'accepted_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));

    return $id;
}

it('keeps one pending invitation per email and organization, ignoring case', function (): void {
    $organization = Organization::factory()->create();
    insertInvitation($organization, 'ada@example.com');

    expect(fn () => insertInvitation($organization, 'ADA@example.com'))->toThrow(QueryException::class);
});

it('allows a new invitation once the previous one is accepted or in another organization', function (): void {
    $organization = Organization::factory()->create();
    insertInvitation($organization, 'ada@example.com', ['accepted_at' => now()]);
    insertInvitation($organization, 'ada@example.com');
    insertInvitation(Organization::factory()->create(), 'ada@example.com');

    expect(DB::table('invitations')->count())->toBe(3);
});

it('refuses a reused token hash', function (): void {
    $organization = Organization::factory()->create();
    $hash = hash('sha256', 'same-token');
    insertInvitation($organization, 'ada@example.com', ['token_hash' => $hash]);

    expect(fn () => insertInvitation($organization, 'grace@example.com', ['token_hash' => $hash]))->toThrow(QueryException::class);
});

it('refuses an unknown role', function (): void {
    expect(fn () => insertInvitation(Organization::factory()->create(), 'ada@example.com', ['role' => 'superuser']))->toThrow(QueryException::class);
});

it('keeps the invitation when the inviter leaves and drops it with the organization', function (): void {
    $kept = Organization::factory()->create();
    $deleted = Organization::factory()->create();
    $inviter = User::factory()->create();
    $keptId = insertInvitation($kept, 'ada@example.com', ['invited_by_user_id' => $inviter->id]);
    insertInvitation($deleted, 'grace@example.com');

    $inviter->delete();
    $deleted->delete();

    expect(DB::table('invitations')->pluck('invited_by_user_id', 'id')->all())->toBe([$keptId => null]);
});
