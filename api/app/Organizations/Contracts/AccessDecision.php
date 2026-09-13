<?php

declare(strict_types=1);

namespace App\Organizations\Contracts;

use Illuminate\Auth\Access\Response;

enum AccessDecision: string
{
    case Allowed = 'allowed';
    case Forbidden = 'forbidden';
    case NotMember = 'not_member';

    public function toPolicyResponse(): Response
    {
        return match ($this) {
            self::Allowed => Response::allow(),
            self::Forbidden => Response::deny(),
            self::NotMember => Response::denyAsNotFound(),
        };
    }
}
