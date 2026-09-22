<?php

declare(strict_types=1);

namespace App\Alerting\Http\Requests;

use App\Alerting\Enums\ChannelType;
use App\Alerting\Settings\AlertingSettings;
use App\Alerting\Values\ChannelDraft;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAlertChannelRequest extends FormRequest
{
    public function rules(AlertingSettings $settings): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(ChannelType::class)],
            ...(new AlertChannelFields($settings))->rules(ChannelType::tryFrom($this->string('type')->toString()), partial: false),
        ];
    }

    public function type(): ChannelType
    {
        return ChannelType::from($this->string('type')->toString());
    }

    public function draft(AlertingSettings $settings): ChannelDraft
    {
        return (new AlertChannelFields($settings))->draft($this, $this->type());
    }
}
