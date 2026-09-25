<?php

declare(strict_types=1);

namespace App\StatusPages\Settings;

use App\Shared\Settings\SettingsReader;

final class StatusPagesSettingsParser
{
    public function parse(string $source, array $values): StatusPagesSettings
    {
        $reader = new SettingsReader($source, $values);
        $slugMin = $reader->integer('lengths.slug_min', 1);
        $slugMax = $reader->integer('lengths.slug_max', 1);
        if ($slugMin > $slugMax) {
            throw $reader->invalid('lengths.slug_min', 'must not exceed lengths.slug_max');
        }

        return new StatusPagesSettings(
            limits: new StatusPageLimits(
                maxPagesPerOrganization: $reader->integer('limits.max_pages_per_organization', 1),
                maxMonitorsPerPage: $reader->integer('limits.max_monitors_per_page', 1),
            ),
            lengths: new StatusPageLengths(
                titleMax: $reader->integer('lengths.title_max', 1),
                descriptionMax: $reader->integer('lengths.description_max', 0),
                displayNameMax: $reader->integer('lengths.display_name_max', 1),
                slugMin: $slugMin,
                slugMax: $slugMax,
            ),
            public: new PublicPageSettings(
                cacheSeconds: $reader->integer('public.cache_seconds', 0),
                barDays: $reader->integer('public.bar_days', 1),
                incidentDays: $reader->integer('public.incident_days', 1),
                maxIncidents: $reader->integer('public.max_incidents', 1),
                rateLimitPerMinute: $reader->integer('public.rate_limit_per_minute', 1),
            ),
        );
    }
}
