<?php

declare(strict_types=1);

use App\Monitoring\Models\Monitor;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use App\StatusPages\Models\StatusPage;
use App\StatusPages\Models\StatusPageMonitor;
use Tests\Support\Members;

function statusPageUrl(StatusPage $page): string
{
    return "/api/v1/organizations/{$page->organization_id}/status-pages/{$page->id}";
}

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $inOrganization = ['organization_id' => $this->organization->id];
    $this->api = Monitor::factory()->state($inOrganization)->create(['name' => 'API']);
    $this->web = Monitor::factory()->state($inOrganization)->create(['name' => 'Website']);
    $this->page = StatusPage::factory()->state($inOrganization)->create(['title' => 'Acme', 'slug' => 'acme', 'description' => 'All services']);
    StatusPageMonitor::query()->create(['status_page_id' => $this->page->id, 'monitor_id' => $this->api->id, 'display_name' => 'API', 'position' => 0]);
});

it('changes the page fields and keeps the monitors when none are sent, for the roles that manage pages', function (Role $role): void {
    $this->actingAs(Members::seat($this->organization, $role))
        ->patchJson(statusPageUrl($this->page), ['title' => 'Acme services', 'slug' => 'acme-services', 'is_published' => true])
        ->assertOk()
        ->assertJsonPath('data.title', 'Acme services')
        ->assertJsonPath('data.slug', 'acme-services')
        ->assertJsonPath('data.is_published', true)
        ->assertJsonPath('data.description', 'All services')
        ->assertJsonCount(1, 'data.monitors');
})->with([Role::Owner, Role::Admin, Role::Member]);

it('clears the description with null', function (): void {
    $this->actingAs(Members::seat($this->organization, Role::Member))
        ->patchJson(statusPageUrl($this->page), ['description' => null])
        ->assertOk()
        ->assertJsonPath('data.description', null);
});

it('replaces the monitors with their new order and names', function (): void {
    $this->actingAs(Members::seat($this->organization, Role::Member))
        ->patchJson(statusPageUrl($this->page), ['monitors' => [
            ['monitor_id' => $this->web->id, 'display_name' => 'Web'],
            ['monitor_id' => $this->api->id],
        ]])
        ->assertOk()
        ->assertJsonPath('data.monitors', [
            ['monitor_id' => $this->web->id, 'display_name' => 'Web', 'position' => 0],
            ['monitor_id' => $this->api->id, 'display_name' => 'API', 'position' => 1],
        ]);
    $this->patchJson(statusPageUrl($this->page), ['monitors' => []])->assertOk()->assertJsonPath('data.monitors', []);
});

it('keeps its own slug and refuses the slug of another page', function (): void {
    StatusPage::factory()->create(['slug' => 'taken']);
    $this->actingAs(Members::seat($this->organization, Role::Owner));

    $this->patchJson(statusPageUrl($this->page), ['slug' => 'acme'])->assertOk();
    $this->patchJson(statusPageUrl($this->page), ['slug' => 'taken'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.slug.0', 'Another status page already uses this slug.');
});

it('deletes a page for the roles that manage pages', function (Role $role): void {
    $this->actingAs(Members::seat($this->organization, $role))->deleteJson(statusPageUrl($this->page))->assertNoContent();

    expect(StatusPage::query()->count())->toBe(0)
        ->and(StatusPageMonitor::query()->count())->toBe(0)
        ->and(Monitor::query()->count())->toBe(2);
})->with([Role::Owner, Role::Admin, Role::Member]);

it('refuses a viewer, a non member and a page of another organization', function (): void {
    $foreign = StatusPage::factory()->create();

    $this->actingAs(Members::seat($this->organization, Role::Viewer))->patchJson(statusPageUrl($this->page), ['title' => 'X'])->assertForbidden();
    $this->deleteJson(statusPageUrl($this->page))->assertForbidden();
    $this->actingAs(Members::outsider())->patchJson(statusPageUrl($this->page), ['title' => 'X'])->assertNotFound();
    $this->actingAs(Members::seat($this->organization, Role::Owner));
    $this->patchJson("/api/v1/organizations/{$this->organization->id}/status-pages/{$foreign->id}", ['title' => 'X'])->assertNotFound();
    $this->deleteJson("/api/v1/organizations/{$this->organization->id}/status-pages/{$foreign->id}")->assertNotFound();

    expect(StatusPage::query()->count())->toBe(2)
        ->and($this->page->fresh()?->title)->toBe('Acme');
});
