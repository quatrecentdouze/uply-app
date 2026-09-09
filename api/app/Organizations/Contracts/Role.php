<?php

declare(strict_types=1);

namespace App\Organizations\Contracts;

enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Viewer = 'viewer';
}
