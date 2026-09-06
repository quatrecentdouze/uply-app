<?php

declare(strict_types=1);

return [
    'delivery' => [
        'max_attempts' => 5,
        'backoff_seconds' => [10, 30, 60, 120],
        'messages_per_second_per_webhook' => 1,
        'test_sends_per_minute' => 5,
    ],
    'webhooks' => [
        'timeout_seconds' => 10,
        'connect_timeout_seconds' => 5,
        'discord' => [
            'hosts' => ['discord.com', 'discordapp.com', 'ptb.discord.com', 'canary.discord.com'],
            'path_pattern' => '~^/api/webhooks/[0-9]+/[A-Za-z0-9_-]+$~',
        ],
        'slack' => [
            'hosts' => ['hooks.slack.com'],
            'path_pattern' => '~^/services/[A-Za-z0-9_-]+(/[A-Za-z0-9_-]+)*$~',
        ],
    ],
    'email' => [
        'min_recipients' => 1,
        'max_recipients' => 10,
    ],
    'presentation' => [
        'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
        'colors' => [
            'down' => '#f23b39',
            'up' => '#34b591',
            'test' => '#f36e29',
        ],
        'discord_username' => 'Uply',
        'discord_title_max_length' => 256,
        'discord_description_max_length' => 4096,
        'discord_field_value_max_length' => 1024,
    ],
    'demo_channels_file' => env('DEMO_ALERT_CHANNELS_FILE'),
];
