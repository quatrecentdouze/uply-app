<?php

declare(strict_types=1);

namespace App\Alerting\Http\Resources;

use App\Alerting\Enums\ChannelType;
use App\Alerting\Models\AlertChannel;
use App\Shared\Time\IsoTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AlertChannelResource extends JsonResource
{
    public function __construct(private readonly AlertChannel $channel)
    {
        parent::__construct($channel);
    }

    public function toArray(Request $request): array
    {
        $hint = $this->channel->webhookHint();

        return [
            'id' => $this->channel->id,
            'type' => $this->channel->type->value,
            'name' => $this->channel->name,
            'is_enabled' => $this->channel->is_enabled,
            'disabled_reason' => $this->channel->disabled_reason?->value,
            'recipients' => $this->channel->type === ChannelType::Email ? $this->recipients() : null,
            'webhook_hint' => $hint === null ? null : ['host' => $hint->host, 'last_four' => $hint->lastFour],
            'reminder_interval_minutes' => $this->channel->reminder_interval_minutes,
            'created_at' => IsoTime::format($this->channel->created_at),
            'updated_at' => IsoTime::format($this->channel->updated_at),
        ];
    }

    private function recipients(): array
    {
        $addresses = [];
        foreach ($this->channel->storedRecipients() as $address) {
            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        return $addresses;
    }
}
