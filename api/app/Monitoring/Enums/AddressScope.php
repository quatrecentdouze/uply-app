<?php

declare(strict_types=1);

namespace App\Monitoring\Enums;

enum AddressScope
{
    case Public;
    case Private;
    case Forbidden;
}
