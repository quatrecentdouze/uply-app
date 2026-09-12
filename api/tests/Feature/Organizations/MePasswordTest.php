<?php

declare(strict_types=1);

use App\Organizations\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;

function changePassword(array $input): TestResponse
{
    return test()->putJson('/api/v1/me/password', $input);
}

function fromFrontend(): void
{
    test()->withHeader('Origin', rtrim(config()->string('organizations.frontend_url'), '/'));
}

it('changes the password with the current one', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);
    changePassword([
        'current_password' => 'password',
        'password' => 'new horse battery staple',
        'password_confirmation' => 'new horse battery staple',
    ])->assertNoContent();

    expect(Hash::check('new horse battery staple', $user->refresh()->password))->toBeTrue();
});

it('refuses a wrong current password or a weak new one', function (array $input, string $field): void {
    $user = User::factory()->create();

    $this->actingAs($user);
    changePassword($input)->assertUnprocessable()->assertJsonValidationErrors($field);

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
})->with([
    'wrong current password' => [['current_password' => 'not the password', 'password' => 'new horse battery staple', 'password_confirmation' => 'new horse battery staple'], 'current_password'],
    'too short' => [['current_password' => 'password', 'password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'not confirmed' => [['current_password' => 'password', 'password' => 'new horse battery staple', 'password_confirmation' => 'other horse battery staple'], 'password'],
]);

it('keeps the current session and ends the others', function (): void {
    $user = User::factory()->create();
    $oldSessionHash = Auth::guard('web')->hashPasswordForCookie($user->password);
    fromFrontend();

    $this->actingAs($user)->withSession(['password_hash_web' => $oldSessionHash]);
    changePassword([
        'current_password' => 'password',
        'password' => 'new horse battery staple',
        'password_confirmation' => 'new horse battery staple',
    ])->assertNoContent();
    $this->getJson('/api/v1/me')->assertOk();

    $this->flushSession();
    $this->actingAs($user->refresh())->withSession(['password_hash_web' => $oldSessionHash])->getJson('/api/v1/me')->assertUnauthorized();
});

it('keeps the password change for verified users', function (): void {
    $this->actingAs(User::factory()->unverified()->create());

    changePassword(['current_password' => 'password', 'password' => 'new horse battery staple', 'password_confirmation' => 'new horse battery staple'])
        ->assertForbidden();
});
