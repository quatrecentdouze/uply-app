<?php

declare(strict_types=1);

return [
    'intervals' => [
        'allowed_seconds' => [30, 60, 120, 300, 600, 900, 1800, 3600],
        'default_seconds' => 60,
    ],
    'probe' => [
        'default_timeout_seconds' => 10,
        'max_timeout_seconds' => 30,
        'connect_timeout_seconds' => 5,
        'max_redirects' => 5,
        'body_read_cap_bytes' => 1048576,
        'user_agent' => 'Uply/1.0',
        'allow_private_targets' => env('MONITORING_ALLOW_PRIVATE_TARGETS', false),
    ],
    'monitor_defaults' => [
        'method' => 'get',
        'accepted_status_codes' => ['200-299'],
    ],
    'confirmation' => [
        'failures_to_confirm_down' => 2,
        'successes_to_confirm_up' => 2,
        'recheck_delay_seconds' => 20,
    ],
    'dispatch' => [
        'tick_seconds' => 10,
        'batch_size' => 500,
    ],
    'limits' => [
        'max_monitors_per_organization' => 100,
        'name_max_length' => 100,
        'url_max_length' => 2048,
    ],
    'retention' => [
        'raw_results_days' => 30,
        'partitions_ahead_days' => 3,
        'hourly_stats_days' => 400,
    ],
];
