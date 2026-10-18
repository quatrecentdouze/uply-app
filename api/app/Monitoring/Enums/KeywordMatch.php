<?php

declare(strict_types=1);

namespace App\Monitoring\Enums;

enum KeywordMatch: string
{
    case Present = 'present';
    case Absent = 'absent';
}
