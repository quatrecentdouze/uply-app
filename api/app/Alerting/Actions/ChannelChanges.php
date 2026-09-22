<?php

declare(strict_types=1);

namespace App\Alerting\Actions;

use App\Alerting\Enums\ChannelType;
use App\Alerting\Models\AlertChannel;
use App\Alerting\Values\ChannelDraft;

final class ChannelChanges
{
    private const bool ENABLED_WHEN_CREATED = true;

    public function create(string $organizationId, ChannelType $type, ChannelDraft $draft): AlertChannel
    {
        $channel = new AlertChannel;
        $channel->organization_id = $organizationId;
        $channel->type = $type;
        $channel->is_enabled = self::ENABLED_WHEN_CREATED;
        $channel->applyDraft($draft);
        $channel->save();

        return $channel->refresh();
    }

    public function update(AlertChannel $channel, ChannelDraft $draft): AlertChannel
    {
        $channel->applyDraft($draft);
        $channel->save();

        return $channel;
    }

    public function delete(AlertChannel $channel): void
    {
        $channel->delete();
    }
}
