<?php

declare(strict_types=1);

use App\Organizations\Messages\AccountMailBuilder;
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

it('builds the password reset mail from the texts', function (): void {
    $mail = app(AccountMailBuilder::class)->passwordReset('Ada', 'http://localhost:5173/reset-password?token=t&email=e', 45);

    expect($mail->subject)->toBe(__('organizations.mail.reset.subject'))
        ->and($mail->introLines)->toBe([__('organizations.mail.reset.intro')])
        ->and($mail->actionText)->toBe(__('organizations.mail.reset.action'))
        ->and($mail->outroLines[0] ?? '')->toContain('45');
});

it('greets without a name when none is known', function (): void {
    $mail = app(AccountMailBuilder::class)->verification(null, 'http://localhost:5173/v', 60);

    expect($mail->greeting)->toBe(__('organizations.mail.greeting_anonymous'));
});

it('leaves no raw text key in the account mails', function (MailMessage $mail): void {
    foreach (mailTexts($mail) as $text) {
        expect($text)->toBeString()->not->toStartWith('organizations.');
    }
})->with([
    'verification' => fn (): MailMessage => app(AccountMailBuilder::class)->verification('Ada', 'http://localhost:5173/v', 60),
    'password reset' => fn (): MailMessage => app(AccountMailBuilder::class)->passwordReset('Ada', 'http://localhost:5173/r', 60),
]);
