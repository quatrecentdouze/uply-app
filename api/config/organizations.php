<?php

declare(strict_types=1);

return [
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
    'users' => [
        'name_max_length' => 80,
    ],
    'passwords' => [
        'min_length' => 12,
        'max_length' => 255,
    ],
    'organizations' => [
        'max_owned_per_user' => 10,
        'name_min_length' => 2,
        'name_max_length' => 80,
        'slug_min_length' => 3,
        'slug_max_length' => 48,
    ],
    'invitations' => [
        'valid_days' => 7,
        'max_pending_per_organization' => 50,
        'token_length' => 40,
    ],
    'rate_limits' => [
        'login_per_minute' => 5,
        'two_factor_per_minute' => 5,
    ],
    'roles' => [
        'owner' => [
            'monitors.view',
            'incidents.view',
            'status-pages.view',
            'alert-channels.view',
            'monitors.manage',
            'status-pages.manage',
            'alert-channels.manage',
            'members.manage',
            'organization.manage',
            'organization.delete',
        ],
        'admin' => [
            'monitors.view',
            'incidents.view',
            'status-pages.view',
            'alert-channels.view',
            'monitors.manage',
            'status-pages.manage',
            'alert-channels.manage',
            'members.manage',
            'organization.manage',
        ],
        'member' => [
            'monitors.view',
            'incidents.view',
            'status-pages.view',
            'alert-channels.view',
            'monitors.manage',
            'status-pages.manage',
        ],
        'viewer' => [
            'monitors.view',
            'incidents.view',
            'status-pages.view',
            'alert-channels.view',
        ],
    ],
];
