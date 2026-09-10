<?php

declare(strict_types=1);

use App\Organizations\Models\User;
use App\Organizations\Notifications\VerifyEmailNotification;
use App\Shared\QueueName;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

function verificationLinkSentTo(User $user): string
{
    $link = '';
    Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use ($user, &$link): bool {
        $link = (string) $notification->toMail($user)->actionUrl;

        return true;
    });

    return $link;
}

function frontendUrl(string $path = ''): string
{
    return rtrim(config()->string('organizations.frontend_url'), '/').$path;
}

it('queues one verification mail on the default queue after sign up', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'correct horse battery',
        'password_confirmation' => 'correct horse battery',
    ])->assertCreated();

    $user = User::query()->sole();
    Notification::assertSentToTimes($user, VerifyEmailNotification::class, 1);
    Notification::assertSentTo($user, VerifyEmailNotification::class, fn (VerifyEmailNotification $notification): bool => $notification instanceof ShouldQueue
        && $notification->queue === QueueName::Default->value
        && $notification->via($user) === ['mail']);
});

it('signs the verification link under the frontend url and verifies the user', function (): void {
    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();
    $link = verificationLinkSentTo($user);

    expect($link)->toStartWith(frontendUrl("/api/v1/auth/email/verify/{$user->id}/"))
        ->and($link)->toContain('signature=');

    $this->actingAs($user)->get($link)->assertRedirect(frontendUrl('?verified=1'));
    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
});

it('sends a guest who opens the link to the frontend login', function (): void {
    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();

    $this->get(verificationLinkSentTo($user))->assertRedirect(frontendUrl('/login'));
    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('refuses a tampered verification link', function (): void {
    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();

    $this->actingAs($user)->get(verificationLinkSentTo($user).'0')->assertForbidden();
    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('sends the verification mail again on request', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->postJson('/api/v1/auth/email/verification-notification')->assertAccepted();

    Notification::assertSentToTimes($user, VerifyEmailNotification::class, 1);
});
