<?php

declare(strict_types=1);

use App\Organizations\Models\User;
use Illuminate\Testing\TestResponse;

function attemptLogin(string $email, string $password): TestResponse
{
    return test()->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password]);
}

function failLogins(string $email, int $times): void
{
    foreach (range(1, $times) as $attempt) {
        attemptLogin($email, 'wrong horse battery')->assertUnprocessable();
    }
}

beforeEach(function (): void {
    User::factory()->create(['email' => 'ada@example.com', 'password' => 'correct horse battery']);
    User::factory()->create(['email' => 'grace@example.com', 'password' => 'correct horse battery']);
});

it('refuses the login once the minute budget of an email is spent', function (): void {
    failLogins('ada@example.com', config()->integer('organizations.rate_limits.login_per_minute'));

    attemptLogin('ADA@example.com', 'correct horse battery')->assertTooManyRequests();
    $this->assertGuest();
});

it('keeps a separate budget for another email from the same address', function (): void {
    failLogins('ada@example.com', config()->integer('organizations.rate_limits.login_per_minute'));

    attemptLogin('grace@example.com', 'correct horse battery')->assertOk();
});

it('opens the login again after a minute', function (): void {
    failLogins('ada@example.com', config()->integer('organizations.rate_limits.login_per_minute'));

    $this->travel(61)->seconds();

    attemptLogin('ada@example.com', 'correct horse battery')->assertOk();
});
