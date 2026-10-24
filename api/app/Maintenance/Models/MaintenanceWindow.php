<?php

declare(strict_types=1);

namespace App\Maintenance\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable(['organization_id', 'name', 'starts_at', 'ends_at', 'created_by_user_id'])]
final class MaintenanceWindow extends Model
{
    use HasFactory;
    use HasUlids;

    public function startsAt(): CarbonImmutable
    {
        return $this->starts_at ?? throw new LogicException("maintenance window {$this->id} has no start");
    }

    public function endsAt(): CarbonImmutable
    {
        return $this->ends_at ?? throw new LogicException("maintenance window {$this->id} has no end");
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
