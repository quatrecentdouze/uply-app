<?php

declare(strict_types=1);

use App\Incidents\Models\Incident;
use App\Maintenance\Models\MaintenanceWindow;
use App\Maintenance\Models\MaintenanceWindowMonitor;
use App\Monitoring\Enums\MonitorState;
use App\Monitoring\Models\Monitor;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use App\StatusPages\Models\StatusPage;
use App\StatusPages\Models\StatusPageMonitor;
use App\StatusPages\Settings\StatusPagesSettings;
use Tests\Support\CheckHistory;
use Tests\Support\Members;

const PUBLIC_PAGE_URL = '/api/v1/public/status-pages/acme';

function showOnPage(StatusPage $page, Monitor $monitor, string $displayName, int $position): void
{
    StatusPageMonitor::query()->create(['status_page_id' => $page->id, 'monitor_id' => $monitor->id, 'display_name' => $displayName, 'position' => $position]);
}

beforeEach(function (): void {
    $this->clock = freezeClock('2027-03-10 12:00:00');
    $this->organization = Organization::factory()->create();
    $inOrganization = ['organization_id' => $this->organization->id];
    $this->api = Monitor::factory()->state($inOrganization)->create(['name' => 'Internal API name', 'url' => 'https://secret-api.internal.example.com/health', 'state' => MonitorState::Down]);
    $this->web = Monitor::factory()->state($inOrganization)->create(['name' => 'Website', 'url' => 'https://www.example.com/', 'state' => MonitorState::Up]);
    $this->cron = Monitor::factory()->state($inOrganization)->paused()->create(['name' => 'Cron', 'url' => 'https://cron.example.com/', 'state' => MonitorState::Up]);
    Monitor::factory()->state($inOrganization)->create(['name' => 'Not shown', 'state' => MonitorState::Down]);
    $this->page = StatusPage::factory()->state($inOrganization)->published()->create(['slug' => 'acme', 'title' => 'Acme status', 'description' => 'Our services']);
    showOnPage($this->page, $this->web, 'Website', 0);
    showOnPage($this->page, $this->api, 'Public API', 1);
    showOnPage($this->page, $this->cron, 'Nightly jobs', 2);
    $now = $this->clock->now();
    CheckHistory::hour($this->api->id, $now->setTime(0, 0), 10, 5);
    CheckHistory::hour($this->api->id, $now->subDays(89)->setTime(6, 0), 10, 10);
    CheckHistory::hour($this->api->id, $now->subDays(90)->setTime(6, 0), 10, 0);
    $this->openIncident = Incident::factory()->create([
        'monitor_id' => $this->api->id,
        'started_at' => $now->subMinutes(20),
        'cause_failure_kind' => 'tls',
        'cause_http_status' => null,
        'cause_message' => 'SSL certificate problem: self-signed certificate at secret-api.internal.example.com',
    ]);
    Incident::factory()->create(['monitor_id' => $this->web->id, 'started_at' => $now->subDays(3), 'resolved_at' => $now->subDays(3)->addSeconds(125)]);
    Incident::factory()->create(['monitor_id' => $this->web->id, 'started_at' => $now->subDays(20), 'resolved_at' => $now->subDays(19)]);
});

it('shows a published page without a session', function (): void {
    $response = $this->getJson(PUBLIC_PAGE_URL)
        ->assertOk()
        ->assertJsonPath('data.title', 'Acme status')
        ->assertJsonPath('data.description', 'Our services')
        ->assertJsonPath('data.overall_state', 'partial_outage')
        ->assertJsonCount(3, 'data.monitors')
        ->assertJsonPath('data.monitors.0.id', $this->web->id)
        ->assertJsonPath('data.monitors.0.display_name', 'Website')
        ->assertJsonPath('data.monitors.0.state', 'up')
        ->assertJsonPath('data.monitors.0.uptime_90d', null)
        ->assertJsonPath('data.monitors.1.display_name', 'Public API')
        ->assertJsonPath('data.monitors.1.state', 'down')
        ->assertJsonPath('data.monitors.1.uptime_90d', 75)
        ->assertJsonCount(90, 'data.monitors.1.bars')
        ->assertJsonPath('data.monitors.1.bars.0', ['date' => '2026-12-11', 'uptime_percent' => 100, 'maintenance' => false])
        ->assertJsonPath('data.monitors.1.bars.1', ['date' => '2026-12-12', 'uptime_percent' => null, 'maintenance' => false])
        ->assertJsonPath('data.monitors.1.bars.89', ['date' => '2027-03-10', 'uptime_percent' => 50, 'maintenance' => false])
        ->assertJsonPath('data.monitors.2.state', 'paused')
        ->assertJsonCount(2, 'data.incidents')
        ->assertJsonPath('data.incidents.0', ['display_name' => 'Public API', 'started_at' => '2027-03-10T11:40:00Z', 'resolved_at' => null, 'duration_seconds' => null])
        ->assertJsonPath('data.incidents.1', ['display_name' => 'Website', 'started_at' => '2027-03-07T12:00:00Z', 'resolved_at' => '2027-03-07T12:02:05Z', 'duration_seconds' => 125])
        ->assertHeader('Cache-Control', 'max-age=30, public');

    $body = (string) $response->getContent();
    expect($body)->not->toContain('example.com')
        ->not->toContain('http')
        ->not->toContain('SSL certificate')
        ->not->toContain('failure_kind')
        ->not->toContain('tls')
        ->not->toContain('Internal API name')
        ->not->toContain('Not shown')
        ->not->toContain($this->organization->id)
        ->not->toContain($this->openIncident->id);
});

it('says major outage when every watched monitor is down and operational when none is', function (): void {
    $this->web->forceFill(['state' => MonitorState::Down])->save();
    $this->getJson(PUBLIC_PAGE_URL)->assertOk()->assertJsonPath('data.overall_state', 'major_outage');

    $this->clock->advance(31);
    $this->api->forceFill(['state' => MonitorState::Up])->save();
    $this->web->forceFill(['state' => MonitorState::Up])->save();
    $this->getJson(PUBLIC_PAGE_URL)->assertOk()->assertJsonPath('data.overall_state', 'operational');
});

it('shows a monitor in an active window as in maintenance, leaves it out of the overall state and marks the days with maintenance', function (): void {
    $window = MaintenanceWindow::factory()->between('2027-03-10 11:30:00', '2027-03-10 13:00:00')->create(['organization_id' => $this->organization->id]);
    MaintenanceWindowMonitor::query()->create(['maintenance_window_id' => $window->id, 'monitor_id' => $this->api->id]);
    $upcoming = MaintenanceWindow::factory()->between('2027-03-10 15:00:00', '2027-03-10 16:00:00')->create(['organization_id' => $this->organization->id]);
    MaintenanceWindowMonitor::query()->create(['maintenance_window_id' => $upcoming->id, 'monitor_id' => $this->web->id]);
    CheckHistory::hour($this->api->id, $this->clock->now()->setTime(11, 0), 0, 0, maintenance: 30);

    $this->getJson(PUBLIC_PAGE_URL)
        ->assertOk()
        ->assertJsonPath('data.overall_state', 'operational')
        ->assertJsonPath('data.monitors.0.state', 'up')
        ->assertJsonPath('data.monitors.1.state', 'maintenance')
        ->assertJsonPath('data.monitors.1.bars.89', ['date' => '2027-03-10', 'uptime_percent' => 50, 'maintenance' => true])
        ->assertJsonPath('data.monitors.1.bars.88.maintenance', false);
});

it('answers 404 for an unpublished or unknown page', function (string $slug): void {
    StatusPage::factory()->create(['slug' => 'draft']);

    $response = $this->getJson("/api/v1/public/status-pages/{$slug}")->assertNotFound();

    expect((string) $response->getContent())->not->toContain('Acme')->not->toContain('draft-title');
})->with(['draft', 'unknown-page', 'UPPER']);

it('serves the cached page for 30 seconds', function (): void {
    $this->getJson(PUBLIC_PAGE_URL)->assertJsonPath('data.title', 'Acme status');
    StatusPage::query()->whereKey($this->page->id)->update(['title' => 'Changed behind the cache']);

    $this->clock->advance(29);
    $this->getJson(PUBLIC_PAGE_URL)->assertJsonPath('data.title', 'Acme status');
    $this->clock->advance(2);
    $this->getJson(PUBLIC_PAGE_URL)->assertJsonPath('data.title', 'Changed behind the cache');
});

it('drops the cached page when the page is changed, unpublished or deleted', function (): void {
    $this->getJson(PUBLIC_PAGE_URL)->assertOk();
    $this->actingAs(Members::seat($this->organization, Role::Member));

    $this->patchJson("/api/v1/organizations/{$this->organization->id}/status-pages/{$this->page->id}", ['title' => 'Acme services'])->assertOk();
    $this->getJson(PUBLIC_PAGE_URL)->assertJsonPath('data.title', 'Acme services');
    $this->patchJson("/api/v1/organizations/{$this->organization->id}/status-pages/{$this->page->id}", ['is_published' => false])->assertOk();
    $this->getJson(PUBLIC_PAGE_URL)->assertNotFound();
    $this->patchJson("/api/v1/organizations/{$this->organization->id}/status-pages/{$this->page->id}", ['is_published' => true])->assertOk();
    $this->getJson(PUBLIC_PAGE_URL)->assertOk();
    $this->deleteJson("/api/v1/organizations/{$this->organization->id}/status-pages/{$this->page->id}")->assertNoContent();
    $this->getJson(PUBLIC_PAGE_URL)->assertNotFound();
});

it('limits the reads of one address', function (): void {
    config(['status_pages.public.rate_limit_per_minute' => 2]);
    app()->forgetInstance(StatusPagesSettings::class);

    $this->getJson(PUBLIC_PAGE_URL)->assertOk();
    $this->getJson(PUBLIC_PAGE_URL)->assertOk();
    $this->getJson(PUBLIC_PAGE_URL)->assertTooManyRequests();
});
