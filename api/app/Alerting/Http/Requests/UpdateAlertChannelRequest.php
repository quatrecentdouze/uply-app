<?php

declare(strict_types=1);

namespace App\Alerting\Http\Requests;

use App\Alerting\Models\AlertChannel;
use App\Alerting\Settings\AlertingSettings;
use App\Alerting\Storage\AlertChannelQuery;
use App\Alerting\Values\ChannelDraft;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateAlertChannelRequest extends FormRequest
{
    private ?AlertChannel $channel = null;

    public function rules(AlertingSettings $settings, AlertChannelQuery $channels): array
    {
        return [
            'type' => ['prohibited'],
            ...(new AlertChannelFields($settings))->rules($this->channel($channels)->type, partial: true),
        ];
    }

    public function channel(AlertChannelQuery $channels): AlertChannel
    {
        return $this->channel ??= $channels->get($this->routeText('organization'), $this->routeText('channel'));
    }

    public function draft(AlertingSettings $settings, AlertChannelQuery $channels): ChannelDraft
    {
        return (new AlertChannelFields($settings))->draft($this, $this->channel($channels)->type);
    }

    private function routeText(string $parameter): string
    {
        $value = $this->route($parameter);

        return is_string($value) ? $value : '';
    }
}
