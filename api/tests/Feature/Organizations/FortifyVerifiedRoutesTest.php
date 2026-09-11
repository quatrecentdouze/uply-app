<?php

declare(strict_types=1);

use App\Organizations\Models\User;

it('keeps password confirmation and two factor for verified users', function (string $method, string $uri): void {
    $this->actingAs(User::factory()->unverified()->create())
        ->json($method, $uri, ['password' => 'password'])
        ->assertForbidden();
})->with([
    'confirmed password status' => ['GET', '/api/v1/auth/user/confirmed-password-status'],
    'confirm password' => ['POST', '/api/v1/auth/user/confirm-password'],
    'enable' => ['POST', '/api/v1/auth/user/two-factor-authentication'],
    'confirm' => ['POST', '/api/v1/auth/user/confirmed-two-factor-authentication'],
    'disable' => ['DELETE', '/api/v1/auth/user/two-factor-authentication'],
    'qr code' => ['GET', '/api/v1/auth/user/two-factor-qr-code'],
    'secret key' => ['GET', '/api/v1/auth/user/two-factor-secret-key'],
    'recovery codes' => ['GET', '/api/v1/auth/user/two-factor-recovery-codes'],
    'new recovery codes' => ['POST', '/api/v1/auth/user/two-factor-recovery-codes'],
]);

it('lets an unverified user log out', function (): void {
    $this->actingAs(User::factory()->unverified()->create())
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    $this->assertGuest();
});

it('lets a verified user confirm the password', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson('/api/v1/auth/user/confirm-password', ['password' => 'password'])
        ->assertCreated();
});
