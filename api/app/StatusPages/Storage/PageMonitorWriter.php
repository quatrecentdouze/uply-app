<?php

declare(strict_types=1);

namespace App\StatusPages\Storage;

use App\Monitoring\Contracts\MonitorDirectory;
use App\Monitoring\Contracts\MonitorView;
use App\StatusPages\Models\StatusPage;
use App\StatusPages\Models\StatusPageMonitor;
use App\StatusPages\Rules\StatusPageRules;
use App\StatusPages\Settings\StatusPagesSettings;
use App\StatusPages\Values\PageMonitorEntry;

final readonly class PageMonitorWriter
{
    public function __construct(
        private MonitorDirectory $monitors,
        private StatusPagesSettings $settings,
    ) {}

    public function replace(StatusPage $page, array $entries): void
    {
        $ids = [];
        foreach ($entries as $entry) {
            if ($entry instanceof PageMonitorEntry) {
                $ids[] = $entry->monitorId;
            }
        }
        $views = $this->monitors->findMany($ids);

        StatusPageMonitor::query()->where('status_page_id', $page->id)->delete();
        $position = 0;
        foreach ($entries as $entry) {
            if (! $entry instanceof PageMonitorEntry) {
                continue;
            }
            $view = $views[$entry->monitorId] ?? null;
            if (! $view instanceof MonitorView || $view->organizationId !== $page->organization_id) {
                continue;
            }
            StatusPageMonitor::query()->create([
                'status_page_id' => $page->id,
                'monitor_id' => $view->id,
                'display_name' => StatusPageRules::displayName($entry->displayName, $view->name, $this->settings->lengths->displayNameMax),
                'position' => $position++,
            ]);
        }
    }
}
