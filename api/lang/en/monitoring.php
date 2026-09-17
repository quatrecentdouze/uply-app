<?php

declare(strict_types=1);

return [
    'errors' => [
        'monitor_limit_reached' => 'This organization already has the most monitors allowed (:max).',
    ],
    'validation' => [
        'url' => [
            'scheme' => 'The URL must start with http:// or https://.',
            'malformed' => 'The URL is not a valid web address.',
            'blocked_address' => 'The URL points to an address Uply never checks.',
        ],
    ],
];
