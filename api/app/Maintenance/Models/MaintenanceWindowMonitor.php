<?php

declare(strict_types=1);

namespace App\Maintenance\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table('maintenance_window_monitor')]
#[Fillable(['maintenance_window_id', 'monitor_id'])]
final class MaintenanceWindowMonitor extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'monitor_id';

    protected $keyType = 'string';
}
