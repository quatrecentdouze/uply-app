<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;

final class AuthenticatedUserId
{
    public static function of(Authenticatable $user): string
    {
        $id = $user->getAuthIdentifier();

        return is_string($id) ? $id : throw new AuthenticationException;
    }
}
