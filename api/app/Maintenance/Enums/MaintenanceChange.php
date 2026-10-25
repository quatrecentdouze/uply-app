<?php

declare(strict_types=1);

namespace App\Maintenance\Enums;

enum MaintenanceChange: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Ended = 'ended';
    case Deleted = 'deleted';
}
