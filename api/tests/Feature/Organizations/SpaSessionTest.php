<?php

declare(strict_types=1);

use App\Organizations\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

it('signs up a user and opens the session without creating any organization', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Ada Lovelace',
        'email' => 'Ada@Example.com',
        'password' => 'correct horse battery',
        'password_confirmation' => 'correct horse battery',
    ])->assertCreated();

    $user = User::query()->sole();
    expect($user->email)->toBe('ada@example.com')
        ->and($user->hasVerifiedEmail())->toBeFalse();
    $this->assertAuthenticatedAs($user);
});

it('logs in and out with the session', function (): void {
    $user = User::factory()->create(['email' => 'ada@example.com', 'password' => 'correct horse battery']);

    $this->postJson('/api/v1/auth/login', ['email' => 'ADA@example.com', 'password' => 'correct horse battery'])
        ->assertOk()
        ->assertExactJson(['two_factor' => false]);
    $this->assertAuthenticatedAs($user);

    $this->postJson('/api/v1/auth/logout')->assertNoContent();
    $this->assertGuest();
});

it('refuses a wrong password', function (): void {
    User::factory()->create(['email' => 'ada@example.com', 'password' => 'correct horse battery']);

    $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.com', 'password' => 'wrong horse battery'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
    $this->assertGuest();
});

it('keeps the auth routes under api v1 auth and serves no login page', function (): void {
    $this->get('/login')->assertNotFound();
    $this->get('/api/v1/auth/register')->assertMethodNotAllowed();
});
