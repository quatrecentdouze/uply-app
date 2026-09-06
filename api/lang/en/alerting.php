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
    'test' => [
        'title' => 'Test alert from Uply',
        'summary' => 'This test was sent to the channel :channel. Real alerts arrive here when a monitor goes down or comes back up.',
    ],
    'labels' => [
        'url' => 'URL',
        'cause' => 'Cause',
        'started' => 'Started',
        'back_up' => 'Back up',
    ],
    'link' => 'Open the incident',
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
    ],
    'duration' => [
        'days' => ':count d',
        'hours' => ':count h',
        'minutes' => ':count min',
        'seconds' => ':count s',
    ],
];
