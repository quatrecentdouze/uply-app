<?php

declare(strict_types=1);

namespace App\StatusPages\Actions;

use App\Incidents\Contracts\IncidentSummary;
use App\Maintenance\Contracts\ScheduledWindow;
use App\Monitoring\Contracts\DailyUptime;
use App\Monitoring\Contracts\MonitorView;
use App\Monitoring\Contracts\UptimeHistory;
use App\Shared\Clock;
use App\Shared\Time\IsoTime;
use App\StatusPages\Enums\PublicMonitorState;
use App\StatusPages\Models\StatusPage;
use App\StatusPages\Models\StatusPageMonitor;
use App\StatusPages\Rules\StatusPageRules;
use App\StatusPages\Settings\StatusPagesSettings;
use App\StatusPages\Values\PublicBar;
use App\StatusPages\Values\PublicIncident;
use App\StatusPages\Values\PublicMonitor;
use App\StatusPages\Values\PublicPage;

final readonly class PublicPageBuilder
{
    private const string BAR_DATE_FORMAT = 'Y-m-d';

    public function __construct(
        private PublicPageSources $sources,
        private StatusPagesSettings $settings,
        private Clock $clock,
    ) {}

    public function build(StatusPage $page): PublicPage
    {
        $now = $this->clock->now();
        $public = $this->settings->public;
        $names = [];
        foreach ($page->monitors as $entry) {
            if ($entry instanceof StatusPageMonitor) {
                $names[$entry->monitor_id] = $entry->display_name;
            }
        }
        $ids = array_keys($names);
        $views = $this->sources->monitors->findMany($ids);
        $histories = $this->sources->uptimes->daily($ids, $now, $public->barDays);
        $windows = $this->sources->maintenance->windowsFor($ids, $now);

        $monitors = [];
        $states = [];
        foreach ($names as $monitorId => $displayName) {
            $view = $views[$monitorId] ?? null;
            if (! $view instanceof MonitorView) {
                continue;
            }
            $history = $histories[$monitorId] ?? null;
            $window = $windows[$monitorId] ?? null;
            $state = PublicMonitorState::of($view->status, $window instanceof ScheduledWindow && $window->isActive);
            $states[] = $state;
            $monitors[] = $this->monitor($view, $displayName, $state, $history instanceof UptimeHistory ? $history : null);
        }

        $incidents = [];
        foreach ($this->sources->incidents->since($ids, StatusPageRules::incidentsSince($now, $public->incidentDays), $public->maxIncidents) as $incident) {
            if ($incident instanceof IncidentSummary && isset($names[$incident->monitorId])) {
                $incidents[] = new PublicIncident($names[$incident->monitorId], IsoTime::format($incident->startedAt) ?? '', IsoTime::format($incident->resolvedAt), $incident->durationSeconds);
            }
        }

        return new PublicPage($page->title, $page->description, StatusPageRules::overallState($states)->value, $monitors, $incidents);
    }

    private function monitor(MonitorView $view, string $displayName, PublicMonitorState $state, ?UptimeHistory $history): PublicMonitor
    {
        $bars = [];
        foreach ($history->days ?? [] as $day) {
            if ($day instanceof DailyUptime) {
                $bars[] = new PublicBar($day->day->format(self::BAR_DATE_FORMAT), $day->uptimePercent, $day->maintenance);
            }
        }

        return new PublicMonitor($view->id, $displayName, $state, $history?->uptimePercent, $bars);
    }
}
