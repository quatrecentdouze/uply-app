<?php

declare(strict_types=1);

use App\Organizations\Contracts\Role;
use App\Organizations\Messages\AccountMailBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;

function mailTexts(MailMessage $mail): array
{
    return [$mail->subject, $mail->greeting, ...$mail->introLines, $mail->actionText, ...$mail->outroLines, $mail->salutation];
}

it('builds the verification mail from the texts', function (): void {
    $mail = app(AccountMailBuilder::class)->verification('Ada', 'http://localhost:5173/api/v1/auth/email/verify/1/abc', 60);

    expect($mail->subject)->toBe(__('organizations.mail.verify.subject'))
        ->and($mail->greeting)->toBe(__('organizations.mail.greeting', ['name' => 'Ada']))
        ->and($mail->actionUrl)->toBe('http://localhost:5173/api/v1/auth/email/verify/1/abc')
        ->and($mail->outroLines)->toBe([__('organizations.mail.verify.outro', ['minutes' => 60])])
        ->and($mail->salutation)->toBe(__('organizations.mail.salutation'));
});

it('builds the password reset mail with its frontend link', function (): void {
    $mail = app(AccountMailBuilder::class)->passwordReset('Ada', 'ada+ops@example.com', 'reset-token', 45);

    expect($mail->actionUrl)->toBe('http://localhost:5173/reset-password?token=reset-token&email=ada%2Bops%40example.com')
        ->and($mail->subject)->toBe(__('organizations.mail.reset.subject'))
        ->and($mail->introLines)->toBe([__('organizations.mail.reset.intro')])
        ->and($mail->actionText)->toBe(__('organizations.mail.reset.action'))
        ->and($mail->outroLines[0] ?? '')->toContain('45');
});

it('greets without a name when none is known', function (): void {
    $mail = app(AccountMailBuilder::class)->verification(null, 'http://localhost:5173/v', 60);

    expect($mail->greeting)->toBe(__('organizations.mail.greeting_anonymous'));
});

it('builds the invitation mail with its frontend link', function (): void {
    $mail = app(AccountMailBuilder::class)->invitation('Ada Lovelace', 'Analytical Engines', Role::Admin, CarbonImmutable::parse('2026-11-05 10:30:00'), 'invitation-token');

    expect($mail->actionUrl)->toBe('http://localhost:5173/invitations/invitation-token')
        ->and($mail->greeting)->toBe(__('organizations.mail.greeting_anonymous'))
        ->and($mail->subject)->toBe(__('organizations.mail.invitation.subject', ['inviter' => 'Ada Lovelace', 'organization' => 'Analytical Engines']))
        ->and($mail->introLines)->toBe([__('organizations.mail.invitation.intro', [
            'inviter' => 'Ada Lovelace',
            'organization' => 'Analytical Engines',
            'role' => __('organizations.roles.admin'),
        ])])
        ->and($mail->outroLines)->toBe([__('organizations.mail.invitation.outro', ['expires' => '2026-11-05 10:30'])]);
});

it('leaves no raw text key in the account mails', function (MailMessage $mail): void {
    foreach (mailTexts($mail) as $text) {
        expect($text)->toBeString()->not->toStartWith('organizations.')->not->toMatch('/:[a-z]+/');
    }
})->with([
    'verification' => fn (): MailMessage => app(AccountMailBuilder::class)->verification('Ada', 'http://localhost:5173/v', 60),
    'password reset' => fn (): MailMessage => app(AccountMailBuilder::class)->passwordReset('Ada', 'ada@example.com', 'reset-token', 60),
    'invitation' => fn (): MailMessage => app(AccountMailBuilder::class)->invitation('Ada', 'Acme', Role::Viewer, CarbonImmutable::parse('2026-11-05 10:30:00'), 'token'),
]);
