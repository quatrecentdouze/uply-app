<?php

declare(strict_types=1);

namespace App\Organizations\Actions;

use App\Organizations\Models\User;
use App\Organizations\Settings\OrganizationsSettings;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final readonly class AccountRules
{
    private const int EMAIL_MAX_LENGTH = 255;

    public function __construct(
        private OrganizationsSettings $settings,
    ) {}

    public function name(): array
    {
        return ['required', 'string', 'min:1', 'max:'.$this->settings->userNameMaxLength];
    }

    public function newEmail(?string $exceptUserId = null): array
    {
        $unique = Rule::unique(User::class, 'email');

        return ['required', 'string', 'email', 'max:'.self::EMAIL_MAX_LENGTH, $exceptUserId === null ? $unique : $unique->ignore($exceptUserId)];
    }

    public function newPassword(): array
    {
        return ['required', 'string', Password::min($this->settings->passwords->minLength)->max($this->settings->passwords->maxLength), 'confirmed'];
    }

    public static function normalizedEmail(mixed $email): mixed
    {
        return is_string($email) ? mb_strtolower(trim($email)) : $email;
    }
}
