<?php

declare(strict_types=1);

use App\Organizations\Models\User;

function spendApiBudget(string $uri): void
{
    foreach (range(1, config()->integer('api.rate_limit_per_minute')) as $request) {
        test()->getJson($uri)->assertHeader('X-RateLimit-Limit', (string) config()->integer('api.rate_limit_per_minute'));
    }
}

it('limits the requests of a user per minute', function (): void {
    $ada = User::factory()->create();
    $grace = User::factory()->create();

    $this->actingAs($ada);
    spendApiBudget('/api/v1/me');
    $this->getJson('/api/v1/me')->assertTooManyRequests();

    $this->actingAs($grace)->getJson('/api/v1/me')->assertOk();
});

it('limits the requests of a guest per address', function (): void {
    spendApiBudget('/api/v1/invitations/unknownToken');

    $this->getJson('/api/v1/invitations/unknownToken')->assertTooManyRequests();
});

it('opens the api again after a minute', function (): void {
    $this->actingAs(User::factory()->create());
    spendApiBudget('/api/v1/me');

    $this->travel(61)->seconds();

    $this->getJson('/api/v1/me')->assertOk();
});
