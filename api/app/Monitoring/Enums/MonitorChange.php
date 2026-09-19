<?php

declare(strict_types=1);

namespace App\Monitoring\Enums;

enum MonitorChange: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
}
