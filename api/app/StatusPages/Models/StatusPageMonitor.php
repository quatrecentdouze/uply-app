<?php

declare(strict_types=1);

namespace App\StatusPages\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['status_page_id', 'monitor_id', 'display_name', 'position'])]
final class StatusPageMonitor extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'monitor_id';

    protected $keyType = 'string';
}
