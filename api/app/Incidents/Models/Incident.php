<?php

declare(strict_types=1);

namespace App\Incidents\Models;

use App\Incidents\Rules\IncidentRules;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable([
    'organization_id',
    'monitor_id',
    'started_at',
    'resolved_at',
    'cause_failure_kind',
    'cause_http_status',
    'cause_message',
])]
final class Incident extends Model
{
    use HasFactory;
    use HasUlids;

    public function startedAt(): CarbonImmutable
    {
        return $this->started_at ?? throw new LogicException("incident {$this->id} has no start time");
    }

    public function durationSeconds(): ?int
    {
        return $this->resolved_at === null ? null : IncidentRules::durationSeconds($this->startedAt(), $this->resolved_at);
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
