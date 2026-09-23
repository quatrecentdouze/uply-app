<?php

declare(strict_types=1);

return [
    'limits' => [
        'max_pages_per_organization' => 10,
        'max_monitors_per_page' => 50,
    ],
    'lengths' => [
        'title_max' => 80,
        'description_max' => 500,
        'display_name_max' => 80,
        'slug_min' => 3,
        'slug_max' => 48,
    ],
    'public' => [
        'cache_seconds' => 30,
        'bar_days' => 90,
        'incident_days' => 14,
        'rate_limit_per_minute' => 60,
    ],
];
