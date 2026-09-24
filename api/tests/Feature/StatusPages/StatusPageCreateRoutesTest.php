<?php

declare(strict_types=1);

use App\Monitoring\Models\Monitor;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use App\StatusPages\Models\StatusPage;
use App\StatusPages\Settings\StatusPagesSettings;
use Tests\Support\Members;

function statusPagesUrl(Organization $organization, ?string $pageId = null): string
{
    return "/api/v1/organizations/{$organization->id}/status-pages".($pageId === null ? '' : "/{$pageId}");
}

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $inOrganization = ['organization_id' => $this->organization->id];
    $this->api = Monitor::factory()->state($inOrganization)->create(['name' => 'Public API']);
    $this->docs = Monitor::factory()->state($inOrganization)->create(['name' => str_repeat('Documentation ', 8)]);
});

it('creates a page with its monitors in order for the roles that manage status pages', function (Role $role): void {
    $this->actingAs(Members::seat($this->organization, $role))
        ->postJson(statusPagesUrl($this->organization), [
            'title' => 'Acme status',
            'slug' => 'acme',
            'description' => 'Live state of the Acme services.',
            'is_published' => true,
            'monitors' => [
                ['monitor_id' => $this->docs->id],
                ['monitor_id' => $this->api->id, 'display_name' => 'API'],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Acme status')
        ->assertJsonPath('data.slug', 'acme')
        ->assertJsonPath('data.description', 'Live state of the Acme services.')
        ->assertJsonPath('data.is_published', true)
        ->assertJsonPath('data.monitors.0', ['monitor_id' => $this->docs->id, 'display_name' => mb_substr(trim(str_repeat('Documentation ', 8)), 0, 80), 'position' => 0])
        ->assertJsonPath('data.monitors.1', ['monitor_id' => $this->api->id, 'display_name' => 'API', 'position' => 1]);

    expect(StatusPage::query()->sole()->organization_id)->toBe($this->organization->id);
})->with([Role::Owner, Role::Admin, Role::Member]);

it('creates an unpublished page without monitors by default', function (): void {
    $this->actingAs(Members::seat($this->organization, Role::Member))
        ->postJson(statusPagesUrl($this->organization), ['title' => 'Draft', 'slug' => 'draft-page'])
        ->assertCreated()
        ->assertJsonPath('data.is_published', false)
        ->assertJsonPath('data.description', null)
        ->assertJsonPath('data.monitors', []);
});

it('lists and shows the pages of the organization to every role', function (Role $role): void {
    StatusPage::factory()->create(['organization_id' => $this->organization->id, 'title' => 'Beta', 'slug' => 'beta']);
    $alpha = StatusPage::factory()->create(['organization_id' => $this->organization->id, 'title' => 'Alpha', 'slug' => 'alpha']);
    StatusPage::factory()->create(['title' => 'Elsewhere']);
    $this->actingAs(Members::seat($this->organization, $role));

    $this->getJson(statusPagesUrl($this->organization))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.title', 'Alpha')
        ->assertJsonPath('meta.total', 2);
    $this->getJson(statusPagesUrl($this->organization, $alpha->id))
        ->assertOk()
        ->assertJsonPath('data.slug', 'alpha');
})->with([Role::Owner, Role::Admin, Role::Member, Role::Viewer]);

it('refuses a viewer and hides the pages from a non member and from other organizations', function (): void {
    $foreign = StatusPage::factory()->create();

    $this->actingAs(Members::seat($this->organization, Role::Viewer))
        ->postJson(statusPagesUrl($this->organization), ['title' => 'Status', 'slug' => 'status'])
        ->assertForbidden();
    $this->actingAs(Members::outsider())->getJson(statusPagesUrl($this->organization))->assertNotFound();
    $this->actingAs(Members::seat($this->organization, Role::Owner))->getJson(statusPagesUrl($this->organization, $foreign->id))->assertNotFound();
});

it('validates the page', function (array $payload, string $field): void {
    StatusPage::factory()->create(['slug' => 'taken']);
    $foreignMonitor = Monitor::factory()->create();
    $payload = array_map(fn (mixed $value): mixed => $value === 'foreign monitor' ? [['monitor_id' => $foreignMonitor->id]] : $value, $payload);

    $this->actingAs(Members::seat($this->organization, Role::Owner))
        ->postJson(statusPagesUrl($this->organization), [...['title' => 'Status', 'slug' => 'status-page'], ...$payload])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'no title' => [['title' => ''], 'title'],
    'long title' => [['title' => str_repeat('t', 81)], 'title'],
    'short slug' => [['slug' => 'ab'], 'slug'],
    'long slug' => [['slug' => str_repeat('s', 49)], 'slug'],
    'upper case slug' => [['slug' => 'Status'], 'slug'],
    'slug with a space' => [['slug' => 'my status'], 'slug'],
    'taken slug' => [['slug' => 'taken'], 'slug'],
    'long description' => [['description' => str_repeat('d', 501)], 'description'],
    'monitor of another organization' => [['monitors' => 'foreign monitor'], 'monitors.0.monitor_id'],
    'monitor that is no id' => [['monitors' => [['monitor_id' => 'api']]], 'monitors.0.monitor_id'],
    'long display name' => [['monitors' => [['monitor_id' => '01jexample00000000000000000', 'display_name' => str_repeat('n', 81)]]], 'monitors.0.display_name'],
    'unknown monitor key' => [['monitors' => [['monitor_id' => '01jexample00000000000000000', 'url' => 'x']]], 'monitors.0'],
]);

it('refuses the same monitor twice and more monitors than a page holds', function (): void {
    config(['status_pages.limits.max_monitors_per_page' => 1]);
    app()->forgetInstance(StatusPagesSettings::class);
    $this->actingAs(Members::seat($this->organization, Role::Owner));

    $this->postJson(statusPagesUrl($this->organization), ['title' => 'S', 'slug' => 'twice', 'monitors' => [['monitor_id' => $this->api->id], ['monitor_id' => $this->docs->id]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('monitors');
    config(['status_pages.limits.max_monitors_per_page' => 50]);
    app()->forgetInstance(StatusPagesSettings::class);
    $this->postJson(statusPagesUrl($this->organization), ['title' => 'S', 'slug' => 'twice', 'monitors' => [['monitor_id' => $this->api->id], ['monitor_id' => $this->api->id]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('monitors.1.monitor_id');
});

it('stops at the page limit of the organization', function (): void {
    config(['status_pages.limits.max_pages_per_organization' => 2]);
    app()->forgetInstance(StatusPagesSettings::class);
    StatusPage::factory()->count(2)->create(['organization_id' => $this->organization->id]);

    $this->actingAs(Members::seat($this->organization, Role::Owner))
        ->postJson(statusPagesUrl($this->organization), ['title' => 'Third', 'slug' => 'third'])
        ->assertConflict()
        ->assertJsonPath('code', 'status_page_limit_reached');
});
