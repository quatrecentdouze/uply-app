<?php

declare(strict_types=1);

it('gives what the monitor forms need', function (): void {
    $this->getJson('/api/v1/meta')
        ->assertOk()
        ->assertJsonPath('data.monitoring', [
            'intervals' => ['allowed_seconds' => [30, 60, 120, 300, 600, 900, 1800, 3600], 'default_seconds' => 60],
            'timeouts' => ['min_seconds' => 1, 'max_seconds' => 30, 'default_seconds' => 10],
            'methods' => ['get', 'head', 'post', 'put', 'patch'],
            'body_methods' => ['post', 'put', 'patch'],
            'default_method' => 'get',
            'default_accepted_status_codes' => ['200-299'],
            'keyword_matches' => ['present', 'absent'],
            'default_keyword_match' => 'present',
            'thresholds' => ['min' => 1, 'max' => 10, 'default_failure' => 2, 'default_recovery' => 2],
            'tls' => ['expiry_alert_days' => [14, 7, 1]],
            'refused_header_names' => ['host', 'content-length', 'transfer-encoding', 'connection', 'keep-alive', 'upgrade', 'te', 'trailer', 'expect', 'proxy-authorization', 'proxy-connection'],
            'windows' => ['24h', '7d', '30d'],
            'limits' => ['max_monitors_per_organization' => 100, 'max_accepted_status_ranges' => 20, 'max_request_headers' => 20],
            'lengths' => [
                'monitor_name' => ['min' => 1, 'max' => 100],
                'url' => ['max' => 2048],
                'keyword' => ['min' => 1, 'max' => 200],
                'request_header_name' => ['min' => 1, 'max' => 100],
                'request_header_value' => ['max' => 1000],
                'request_body' => ['max' => 10000],
            ],
        ]);
});
