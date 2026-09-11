<?php

declare(strict_types=1);

use App\Organizations\Models\Organization;
use App\Organizations\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function insertMembership(Organization $organization, User $user, string $role): void
{
    DB::table('memberships')->insert([
        'id' => Str::lower((string) Str::ulid()),
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => $role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('accepts the four roles only', function (): void {
    $organization = Organization::factory()->create();
    foreach (['owner', 'admin', 'member', 'viewer'] as $role) {
        insertMembership($organization, User::factory()->create(), $role);
    }

    $outsider = User::factory()->create();

    expect(DB::table('memberships')->count())->toBe(4)
        ->and(fn () => insertMembership($organization, $outsider, 'superuser'))->toThrow(QueryException::class);
});

it('keeps one membership per user and organization', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    insertMembership($organization, $user, 'member');

    expect(fn () => insertMembership($organization, $user, 'admin'))->toThrow(QueryException::class);
});

it('drops the memberships of a deleted user or organization', function (): void {
    $kept = Organization::factory()->create();
    $deleted = Organization::factory()->create();
    $leaving = User::factory()->create();
    $staying = User::factory()->create();
    insertMembership($kept, $leaving, 'owner');
    insertMembership($kept, $staying, 'member');
    insertMembership($deleted, $staying, 'owner');

    $leaving->delete();
    $deleted->delete();

    expect(DB::table('memberships')->pluck('user_id')->all())->toBe([$staying->id]);
});
