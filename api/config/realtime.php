<?php

declare(strict_types=1);

return [
    'client' => [
        'key' => env('REVERB_APP_KEY') ?: null,
        'host' => env('REVERB_CLIENT_HOST', 'localhost'),
        'port' => (int) env('REVERB_CLIENT_PORT', 6001),
        'scheme' => env('REVERB_CLIENT_SCHEME', 'http'),
    ],
];
