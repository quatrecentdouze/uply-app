<?php

declare(strict_types=1);

return [
    'down' => [
        'title' => ':name is down',
    ],
    'up' => [
        'title' => ':name is back up',
        'downtime' => 'Down for :duration',
    ],
    'reminder' => [
        'title' => ':name is still down',
    ],
    'certificate' => [
        'title' => '{1} :name: TLS certificate expires in :days day|[2,*] :name: TLS certificate expires in :days days',
    ],
    'test' => [
        'title' => 'Test alert from Uply',
        'summary' => 'This test was sent to the channel :channel. Real alerts arrive here when a monitor goes down or comes back up.',
    ],
    'labels' => [
        'url' => 'URL',
        'cause' => 'Cause',
        'started' => 'Started',
        'back_up' => 'Back up',
        'down_for' => 'Down for',
        'expires' => 'Expires',
    ],
    'link' => 'Open the incident',
    'monitor_link' => 'Open the monitor',
    'time_utc' => ':time UTC',
    'cause' => [
        'http_status' => 'HTTP :status',
        'timeout' => 'Timeout',
        'dns' => 'DNS error',
        'connect' => 'Connection error',
        'tls' => 'TLS error',
        'too_many_redirects' => 'Too many redirects',
        'blocked_target' => 'Blocked target',
        'body_too_large' => 'Response too large',
        'unknown' => 'Unknown error',
        'keyword_missing' => 'Keyword not found',
        'keyword_found' => 'Unwanted keyword found',
    ],
    'duration' => [
        'days' => ':count d',
        'hours' => ':count h',
        'minutes' => ':count min',
        'seconds' => ':count s',
    ],
    'errors' => [
        'test_limit_reached' => 'This channel already got the most test alerts allowed for now. Try again in :seconds s.',
    ],
    'validation' => [
        'webhook_url' => [
            'discord' => 'The URL must be a Discord webhook URL like https://discord.com/api/webhooks/...',
            'slack' => 'The URL must be a Slack incoming webhook URL like https://hooks.slack.com/services/...',
        ],
        'recipients' => 'Give :min to :max valid email addresses.',
    ],
];
