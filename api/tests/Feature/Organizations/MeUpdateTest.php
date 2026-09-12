<?php

declare(strict_types=1);

use App\Organizations\Models\User;
use App\Organizations\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    $this->user = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
});

it('renames the user', function (): void {
    $this->actingAs($this->user)->patchJson('/api/v1/me', ['name' => 'Ada King'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Ada King');

    expect($this->user->refresh()->name)->toBe('Ada King')
        ->and($this->user->hasVerifiedEmail())->toBeTrue();
    Notification::assertNothingSent();
});

it('changes the email with the current password and asks to verify it again', function (): void {
    $this->actingAs($this->user)->patchJson('/api/v1/me', ['email' => 'Countess@Example.com', 'current_password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.email', 'countess@example.com')
        ->assertJsonPath('data.email_verified_at', null);

    expect($this->user->refresh()->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($this->user, VerifyEmailNotification::class);
});

it('needs the current password to change the email', function (?string $password): void {
    $this->actingAs($this->user)->patchJson('/api/v1/me', array_filter(['email' => 'countess@example.com', 'current_password' => $password]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    expect($this->user->refresh()->email)->toBe('ada@example.com');
})->with(['missing' => [null], 'wrong' => ['not the password']]);

it('treats the same email in another case as unchanged', function (): void {
    $this->actingAs($this->user)->patchJson('/api/v1/me', ['email' => 'ADA@example.com'])->assertOk();

    expect($this->user->refresh()->hasVerifiedEmail())->toBeTrue();
    Notification::assertNothingSent();
});

it('refuses an email used by another account', function (): void {
    User::factory()->create(['email' => 'grace@example.com']);

    $this->actingAs($this->user)->patchJson('/api/v1/me', ['email' => 'Grace@example.com', 'current_password' => 'password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('bounds the name', function (string $name): void {
    $this->actingAs($this->user)->patchJson('/api/v1/me', ['name' => $name])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
})->with(['empty' => [''], 'too long' => [str_repeat('a', 81)]]);

it('keeps the account changes for verified users', function (): void {
    $this->actingAs(User::factory()->unverified()->create())->patchJson('/api/v1/me', ['name' => 'Ada King'])->assertForbidden();
});
