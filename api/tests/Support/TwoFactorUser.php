<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Organizations\Models\User;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

final readonly class TwoFactorUser
{
    private function __construct(
        public User $user,
        private string $secret,
    ) {}

    public static function create(array $attributes = []): self
    {
        $user = User::factory()->create($attributes);
        app(EnableTwoFactorAuthentication::class)($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return new self($user->refresh(), Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret));
    }

    public function currentCode(): string
    {
        return (new Google2FA)->getCurrentOtp($this->secret);
    }

    public function recoveryCodes(): array
    {
        return $this->user->refresh()->recoveryCodes();
    }
}
