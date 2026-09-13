<?php

declare(strict_types=1);

namespace App\Organizations\Contracts;

enum AccessDecision: string
{
    case Allowed = 'allowed';
    case Forbidden = 'forbidden';
    case NotMember = 'not_member';
}
