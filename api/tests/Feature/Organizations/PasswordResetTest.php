<?php

declare(strict_types=1);

use App\Organizations\Models\User;
use App\Organizations\Notifications\ResetPasswordNotification;
use App\Shared\QueueName;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

function resetLinkSentTo(User $user): string
{
    $link = '';
    Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user, &$link): bool {
        $link = (string) $notification->toMail($user)->actionUrl;

        return true;
    });

    return $link;
}

function resetQuery(string $link): array
{
    parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

    return $query;
}

it('queues the reset mail with a link to the frontend reset page', function (): void {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ADA@example.com'])->assertOk();

    Notification::assertSentTo($user, ResetPasswordNotification::class, fn (ResetPasswordNotification $notification): bool => $notification instanceof ShouldQueue
        && $notification->queue === QueueName::Default->value);
    $link = resetLinkSentTo($user);
    expect($link)->toStartWith(rtrim(config()->string('organizations.frontend_url'), '/').'/reset-password?token=')
        ->and(resetQuery($link)['email'] ?? null)->toBe('ada@example.com');
});

it('resets the password with the token of the link', function (): void {
    $user = User::factory()->create(['email' => 'ada@example.com', 'password' => 'old horse battery staple']);
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ada@example.com'])->assertOk();
    $query = resetQuery(resetLinkSentTo($user));

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $query['token'] ?? '',
        'email' => $query['email'] ?? '',
        'password' => 'new horse battery staple',
        'password_confirmation' => 'new horse battery staple',
    ])->assertOk();

    $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.com', 'password' => 'old horse battery staple'])->assertUnprocessable();
    $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.com', 'password' => 'new horse battery staple'])->assertOk();
});

it('refuses a reset with a wrong token', function (): void {
    User::factory()->create(['email' => 'ada@example.com']);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'not-the-token',
        'email' => 'ada@example.com',
        'password' => 'new horse battery staple',
        'password_confirmation' => 'new horse battery staple',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});
