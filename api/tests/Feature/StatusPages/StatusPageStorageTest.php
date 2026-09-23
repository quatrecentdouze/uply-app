<?php

declare(strict_types=1);

use App\Monitoring\Models\Monitor;
use App\Organizations\Models\Organization;
use App\StatusPages\Models\StatusPage;
use App\StatusPages\Models\StatusPageMonitor;
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

it('keeps the monitors of a page in their order and drops them with the page or the monitor', function (): void {
    $page = StatusPage::factory()->create();
    $first = Monitor::factory()->create(['organization_id' => $page->organization_id]);
    $second = Monitor::factory()->create(['organization_id' => $page->organization_id]);
    StatusPageMonitor::query()->create(['status_page_id' => $page->id, 'monitor_id' => $second->id, 'display_name' => 'Second', 'position' => 1]);
    StatusPageMonitor::query()->create(['status_page_id' => $page->id, 'monitor_id' => $first->id, 'display_name' => 'First', 'position' => 0]);

    expect($page->monitors()->pluck('display_name')->all())->toBe(['First', 'Second']);

    $second->delete();
    expect($page->monitors()->count())->toBe(1);

    $page->delete();
    expect(StatusPageMonitor::query()->count())->toBe(0);
});

it('lists a monitor once per page', function (): void {
    $page = StatusPage::factory()->create();
    $monitor = Monitor::factory()->create(['organization_id' => $page->organization_id]);
    StatusPageMonitor::query()->create(['status_page_id' => $page->id, 'monitor_id' => $monitor->id, 'display_name' => 'API', 'position' => 0]);

    expect(fn () => StatusPageMonitor::query()->create(['status_page_id' => $page->id, 'monitor_id' => $monitor->id, 'display_name' => 'API again', 'position' => 1]))
        ->toThrow(UniqueConstraintViolationException::class);
});
