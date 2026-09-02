<?php

declare(strict_types=1);

namespace App\Monitoring\Models;

use App\Monitoring\Enums\HttpMethod;
use App\Monitoring\Enums\MonitorState;
use App\Monitoring\Values\AcceptedStatusCodes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'organization_id',
    'name',
    'url',
    'method',
    'interval_seconds',
    'timeout_seconds',
    'accepted_status_codes',
    'follow_redirects',
    'is_paused',
    'next_check_at',
])]
final class Monitor extends Model
{
    use HasFactory;
    use HasUlids;

    public function acceptedStatusCodes(): AcceptedStatusCodes
    {
        $texts = $this->getAttribute('accepted_status_codes');

        return AcceptedStatusCodes::parse(is_array($texts) ? $texts : []);
    }

    protected function casts(): array
    {
        return [
            'method' => HttpMethod::class,
            'state' => MonitorState::class,
            'accepted_status_codes' => 'array',
            'follow_redirects' => 'boolean',
            'is_paused' => 'boolean',
            'streak_started_at' => 'immutable_datetime',
            'state_changed_at' => 'immutable_datetime',
            'last_checked_at' => 'immutable_datetime',
            'next_check_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
