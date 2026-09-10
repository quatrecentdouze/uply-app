<?php

declare(strict_types=1);

use App\Organizations\Actions\CreateUser;
use App\Organizations\Actions\ResetUserPassword;
use App\Organizations\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

function validationErrors(Closure $action): array
{
    try {
        $action();
    } catch (ValidationException $exception) {
        return array_keys($exception->errors());
    }

    return [];
}

it('creates a user with a lowercased email and a hashed password', function (): void {
    $user = app(CreateUser::class)->create([
        'name' => 'Ada Lovelace',
        'email' => '  Ada@Example.COM ',
        'password' => 'correct horse battery',
        'password_confirmation' => 'correct horse battery',
    ]);

    expect($user->email)->toBe('ada@example.com')
        ->and(Hash::check('correct horse battery', $user->password))->toBeTrue()
        ->and($user->email_verified_at)->toBeNull();
});

it('refuses an invalid sign up', function (array $input, array $fields): void {
    User::factory()->create(['email' => 'taken@example.com']);
    $valid = ['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'twelve chars', 'password_confirmation' => 'twelve chars'];

    expect(validationErrors(fn () => app(CreateUser::class)->create([...$valid, ...$input])))->toBe($fields);
})->with([
    'password of 11 characters' => [['password' => 'eleven char', 'password_confirmation' => 'eleven char'], ['password']],
    'password not confirmed' => [['password_confirmation' => 'something else'], ['password']],
    'password too long' => [['password' => str_repeat('p', 256), 'password_confirmation' => str_repeat('p', 256)], ['password']],
    'email taken in another case' => [['email' => 'TAKEN@example.com'], ['email']],
    'email invalid' => [['email' => 'ada'], ['email']],
    'name too long' => [['name' => str_repeat('n', 81)], ['name']],
    'name missing' => [['name' => ''], ['name']],
]);

it('resets a password with the same rules', function (): void {
    $user = User::factory()->create();

    app(ResetUserPassword::class)->reset($user, ['password' => 'a brand new secret', 'password_confirmation' => 'a brand new secret']);

    expect(Hash::check('a brand new secret', (string) $user->fresh()?->password))->toBeTrue()
        ->and(validationErrors(fn () => app(ResetUserPassword::class)->reset($user, ['password' => 'short', 'password_confirmation' => 'short'])))->toBe(['password']);
});
