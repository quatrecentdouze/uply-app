<?php

declare(strict_types=1);

return [
    'lengths' => [
        'name_max' => 100,
    ],
    'limits' => [
        'max_open_windows_per_organization' => 50,
        'max_monitors_per_window' => 100,
    ],
    'bounds' => [
        'max_duration_hours' => 168,
        'max_days_ahead' => 90,
        'start_grace_seconds' => 60,
    ],
];
