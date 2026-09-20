<?php

declare(strict_types=1);

namespace App\Monitoring\Actions;

use App\Monitoring\Enums\StatsWindow;
use App\Monitoring\Rules\StatsRules;
use App\Monitoring\Rules\UptimeRules;
use App\Monitoring\Storage\MonitorQuery;
use App\Monitoring\Storage\StatsQuery;
use App\Monitoring\Values\CheckTotals;
use App\Monitoring\Values\MonitorStats;
use App\Monitoring\Values\OrganizationOverview;
use App\Shared\Clock;
use Carbon\CarbonImmutable;

final readonly class StatsReport
{
    public function __construct(
        private StatsQuery $stats,
        private MonitorQuery $monitors,
        private Clock $clock,
    ) {}

    public function organization(string $organizationId, StatsWindow $window): OrganizationOverview
    {
        $now = $this->clock->now();
        $plan = StatsRules::plan($window, $now);
        $monitorIds = $this->monitors->idsIn($organizationId);
        $hours = $this->stats->hourly($monitorIds, $plan);

        return new OrganizationOverview(
            $window,
            $this->monitors->statusCounts($organizationId),
            StatsRules::summary($this->headline($monitorIds, $window, $now, StatsRules::within($plan, $hours))),
            StatsRules::series($plan, $hours),
        );
    }

    public function monitor(string $monitorId, StatsWindow $window): MonitorStats
    {
        $now = $this->clock->now();
        $plan = StatsRules::plan($window, $now);
        $hours = $this->stats->hourly([$monitorId], $plan);

        return new MonitorStats(
            $window,
            StatsRules::summary($this->headline([$monitorId], $window, $now, StatsRules::within($plan, $hours))),
            StatsRules::series($plan, $hours),
            $this->stats->timings($monitorId, UptimeRules::rollingDayStart($now), $now),
        );
    }

    private function headline(array $monitorIds, StatsWindow $window, CarbonImmutable $now, CheckTotals $hourly): CheckTotals
    {
        return StatsRules::headlineFromRawResults($window)
            ? $this->stats->raw($monitorIds, UptimeRules::rollingDayStart($now), $now)
            : $hourly;
    }
}
