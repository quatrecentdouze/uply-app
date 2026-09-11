<?php

declare(strict_types=1);

use App\Organizations\Models\User;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

function confirmPassword(string $password = 'password'): void
{
    test()->postJson('/api/v1/auth/user/confirm-password', ['password' => $password])->assertCreated();
}

function enableTwoFactorOverHttp(): string
{
    confirmPassword();
    test()->postJson('/api/v1/auth/user/two-factor-authentication')->assertOk();

    return (string) test()->getJson('/api/v1/auth/user/two-factor-secret-key')->assertOk()->json('secretKey');
}

it('asks for the password before enabling two factor', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/auth/user/two-factor-authentication')->assertStatus(423);

    expect($user->refresh()->two_factor_secret)->toBeNull();
});

it('refuses a wrong password confirmation', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson('/api/v1/auth/user/confirm-password', ['password' => 'not the password'])
        ->assertUnprocessable();
});

it('turns two factor on only once a code confirms the setup', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $secret = enableTwoFactorOverHttp();

    expect($user->refresh()->hasEnabledTwoFactorAuthentication())->toBeFalse();
    $this->getJson('/api/v1/auth/user/two-factor-qr-code')
        ->assertOk()
        ->assertJsonStructure(['svg', 'url'])
        ->assertJsonPath('url', fn (string $url): bool => str_starts_with($url, 'otpauth://totp/Uply:'));

    $this->postJson('/api/v1/auth/user/confirmed-two-factor-authentication', ['code' => 'abcdef'])->assertUnprocessable();
    $this->postJson('/api/v1/auth/user/confirmed-two-factor-authentication', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertOk();

    expect($user->refresh()->hasEnabledTwoFactorAuthentication())->toBeTrue()
        ->and($user->two_factor_confirmed_at)->not->toBeNull();
});

it('gives eight recovery codes and replaces them on request', function (): void {
    $this->actingAs(User::factory()->create());
    enableTwoFactorOverHttp();

    $codes = $this->getJson('/api/v1/auth/user/two-factor-recovery-codes')->assertOk()->json();
    $this->postJson('/api/v1/auth/user/two-factor-recovery-codes')->assertOk();
    $replaced = $this->getJson('/api/v1/auth/user/two-factor-recovery-codes')->assertOk()->json();

    expect($codes)->toHaveCount(8)
        ->and($replaced)->toHaveCount(8)
        ->and(array_intersect((array) $codes, (array) $replaced))->toBe([]);
});

it('turns two factor off', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    enableTwoFactorOverHttp();

    $this->deleteJson('/api/v1/auth/user/two-factor-authentication')->assertOk();

    $user->refresh();
    expect($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull();
});

it('stores the secret encrypted and never serializes it', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $secret = enableTwoFactorOverHttp();

    $user->refresh();
    expect($user->two_factor_secret)->not->toBe($secret)
        ->and(Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret))->toBe($secret)
        ->and($user->toArray())->not->toHaveKeys(['two_factor_secret', 'two_factor_recovery_codes']);
});
