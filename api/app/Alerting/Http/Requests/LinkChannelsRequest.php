<?php

declare(strict_types=1);

namespace App\Alerting\Http\Requests;

use App\Alerting\Settings\AlertingSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LinkChannelsRequest extends FormRequest
{
    public function rules(AlertingSettings $settings): array
    {
        $organization = $this->route('organization');

        return [
            'channel_ids' => ['present', 'array', 'list', 'max:'.$settings->channels->maxPerMonitor],
            'channel_ids.*' => [
                'string',
                'ulid',
                'distinct',
                Rule::exists('alert_channels', 'id')->where('organization_id', is_string($organization) ? $organization : ''),
            ],
        ];
    }

    public function channelIds(): array
    {
        return array_map(fn (string $id): string => strtolower($id), array_filter($this->array('channel_ids'), 'is_string'));
    }
}
