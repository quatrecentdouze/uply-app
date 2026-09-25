<?php

declare(strict_types=1);

namespace App\StatusPages\Http;

use App\Shared\Meta\MetaSection;
use App\StatusPages\Enums\OverallState;
use App\StatusPages\Http\Requests\StatusPageFields;
use App\StatusPages\Http\Requests\ValidSlug;
use App\StatusPages\Settings\StatusPagesSettings;

final readonly class StatusPagesMetaSection implements MetaSection
{
    public function __construct(private StatusPagesSettings $settings) {}

    public function key(): string
    {
        return 'status_pages';
    }

    public function describe(): array
    {
        $lengths = $this->settings->lengths;

        return [
            'limits' => [
                'max_pages_per_organization' => $this->settings->limits->maxPagesPerOrganization,
                'max_monitors_per_page' => $this->settings->limits->maxMonitorsPerPage,
            ],
            'lengths' => [
                'title' => ['min' => StatusPageFields::TITLE_MIN_LENGTH, 'max' => $lengths->titleMax],
                'description' => ['max' => $lengths->descriptionMax],
                'display_name' => ['max' => $lengths->displayNameMax],
                'slug' => ['min' => $lengths->slugMin, 'max' => $lengths->slugMax, 'pattern' => ValidSlug::PATTERN_BODY],
            ],
            'public' => [
                'bar_days' => $this->settings->public->barDays,
                'incident_days' => $this->settings->public->incidentDays,
                'cache_seconds' => $this->settings->public->cacheSeconds,
            ],
            'overall_states' => array_map(fn (OverallState $state): string => $state->value, OverallState::cases()),
        ];
    }
}
