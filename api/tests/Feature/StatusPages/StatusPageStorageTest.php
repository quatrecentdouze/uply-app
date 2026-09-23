<?php

declare(strict_types=1);

use App\Organizations\Models\Organization;
use App\StatusPages\Models\StatusPage;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

it('stores a status page of an organization, unpublished by default', function (): void {
    $page = StatusPage::factory()->create(['slug' => 'acme-status', 'title' => 'Acme status']);

    expect($page->fresh()?->is_published)->toBeFalse()
        ->and($page->fresh()?->description)->toBeNull()
        ->and($page->id)->toMatch('/^[0-9a-z]{26}$/');
});

it('keeps slugs unique across organizations', function (): void {
    StatusPage::factory()->create(['slug' => 'acme']);

    expect(fn () => StatusPage::factory()->create(['slug' => 'acme']))->toThrow(UniqueConstraintViolationException::class);
});

it('refuses a slug outside lowercase letters, digits and dashes', function (string $slug): void {
    expect(fn () => StatusPage::factory()->create(['slug' => $slug]))->toThrow(QueryException::class);
})->with(['Acme', 'acme status', 'acme_status']);

it('goes away with its organization', function (): void {
    $organization = Organization::factory()->create();
    StatusPage::factory()->create(['organization_id' => $organization->id]);

    $organization->delete();

    expect(StatusPage::query()->count())->toBe(0);
});
