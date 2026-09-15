<?php

declare(strict_types=1);

namespace App\Organizations\Rules;

use SensitiveParameter;

final class InvitationTokenRules
{
    private const string ALGORITHM = 'sha256';

    public static function hash(#[SensitiveParameter] string $token): string
    {
        return hash(self::ALGORITHM, $token);
    }
}
