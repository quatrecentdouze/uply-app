<?php

declare(strict_types=1);

use App\Maintenance\Models\MaintenanceWindow;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;
use Illuminate\Database\QueryException;

it('stores a window of an organization with its period', function (): void {
    $window = MaintenanceWindow::factory()->between('2027-03-10 12:00:00', '2027-03-10 14:00:00')->create(['name' => 'Database upgrade']);

    $stored = $window->fresh();
    expect($stored?->name)->toBe('Database upgrade')
        ->and($stored?->startsAt()->toIso8601ZuluString())->toBe('2027-03-10T12:00:00Z')
        ->and($stored?->endsAt()->toIso8601ZuluString())->toBe('2027-03-10T14:00:00Z')
        ->and($stored?->created_by_user_id)->toBeNull()
        ->and($window->id)->toMatch('/^[0-9a-z]{26}$/');
});

it('refuses an end that is not after the start', function (string $endsAt): void {
    expect(fn () => MaintenanceWindow::factory()->between('2027-03-10 12:00:00', $endsAt)->create())->toThrow(QueryException::class);
})->with(['2027-03-10 12:00:00', '2027-03-10 11:59:59']);

it('keeps the window once its author is deleted', function (): void {
    $author = User::factory()->create();
    $window = MaintenanceWindow::factory()->create(['created_by_user_id' => $author->id]);

    $author->delete();

    expect($window->fresh()?->created_by_user_id)->toBeNull();
});

it('goes away with its organization', function (): void {
    $organization = Organization::factory()->create();
    MaintenanceWindow::factory()->create(['organization_id' => $organization->id]);

    $organization->delete();

    expect(MaintenanceWindow::query()->count())->toBe(0);
});
