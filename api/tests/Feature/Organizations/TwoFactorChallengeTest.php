<?php

declare(strict_types=1);

use Illuminate\Testing\TestResponse;
use Tests\Support\TwoFactorUser;

function startTwoFactorLogin(TwoFactorUser $account): void
{
    test()->postJson('/api/v1/auth/login', ['email' => $account->user->email, 'password' => 'password'])
        ->assertOk()
        ->assertExactJson(['two_factor' => true]);
}

function answerChallenge(array $answer): TestResponse
{
    return test()->postJson('/api/v1/auth/two-factor-challenge', $answer);
}

it('stops the login at the challenge when two factor is on', function (): void {
    startTwoFactorLogin(TwoFactorUser::create());

    $this->assertGuest();
});

it('opens the session with the current code', function (): void {
    $account = TwoFactorUser::create();
    startTwoFactorLogin($account);

    answerChallenge(['code' => $account->currentCode()])->assertNoContent();

    $this->assertAuthenticatedAs($account->user);
});

it('refuses a wrong code', function (): void {
    startTwoFactorLogin(TwoFactorUser::create());

    answerChallenge(['code' => 'abcdef'])->assertUnprocessable()->assertJsonValidationErrors('code');

    $this->assertGuest();
});

it('accepts a recovery code once', function (): void {
    $account = TwoFactorUser::create();
    $recoveryCode = (string) ($account->recoveryCodes()[0] ?? '');

    startTwoFactorLogin($account);
    answerChallenge(['recovery_code' => $recoveryCode])->assertNoContent();
    $this->assertAuthenticatedAs($account->user);
    expect($account->recoveryCodes())->toHaveCount(8)->not->toContain($recoveryCode);

    $this->postJson('/api/v1/auth/logout')->assertNoContent();
    startTwoFactorLogin($account);
    answerChallenge(['recovery_code' => $recoveryCode])->assertUnprocessable()->assertJsonValidationErrors('recovery_code');
    $this->assertGuest();
});

it('limits the challenge answers of a login', function (): void {
    startTwoFactorLogin(TwoFactorUser::create());

    foreach (range(1, config()->integer('organizations.rate_limits.two_factor_per_minute')) as $attempt) {
        answerChallenge(['code' => 'abcdef'])->assertUnprocessable();
    }

    answerChallenge(['code' => 'abcdef'])->assertTooManyRequests();
});
