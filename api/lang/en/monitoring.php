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
        'status_code_range' => 'Each accepted status code must look like 200-299 or 204, between 100 and 599.',
        'refused_header' => 'Uply sets this header itself, a monitor cannot send it.',
        'conflict' => [
            'request_body' => 'Only a POST, PUT or PATCH request sends a body.',
        ],
    ],
];
