<?php

declare(strict_types=1);

namespace App\Alerting\Models;

use App\Alerting\Enums\DeliveryKind;
use App\Alerting\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['alert_channel_id', 'incident_id', 'monitor_id', 'certificate_expires_at', 'certificate_days', 'reminder_number', 'flapping_since', 'kind', 'status', 'attempts', 'last_error', 'sent_at'])]
final class AlertDelivery extends Model
{
    use HasFactory;
    use HasUlids;

    public function isPending(): bool
    {
        return $this->status === DeliveryStatus::Pending;
    }

    protected function casts(): array
    {
        return [
            'kind' => DeliveryKind::class,
            'status' => DeliveryStatus::class,
            'sent_at' => 'immutable_datetime',
            'certificate_expires_at' => 'immutable_datetime',
            'flapping_since' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
