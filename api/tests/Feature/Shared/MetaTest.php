<?php

declare(strict_types=1);
use App\Shared\Settings\ApiSettings;

it('describes the api to anyone', function (): void {
    $this->getJson('/api/v1/meta')
        ->assertOk()
        ->assertJsonPath('data.api.pagination.default_per_page', 25)
        ->assertJsonPath('data.api.pagination.max_per_page', 100)
        ->assertJsonPath('data.api.rate_limit_per_minute', 120);
});

it('lets browsers and the server cache the description', function (): void {
    $response = $this->getJson('/api/v1/meta')->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('public')->toContain('max-age=300');

    config(['api.pagination.default_per_page' => 50]);
    app()->forgetInstance(ApiSettings::class);

    $this->getJson('/api/v1/meta')->assertJsonPath('data.api.pagination.default_per_page', 25);
});
