<?php

declare(strict_types=1);

namespace App\Monitoring\Http\Requests;

use App\Monitoring\Enums\HttpMethod;
use App\Monitoring\Settings\MonitoringSettings;
use Illuminate\Validation\Rule;

final readonly class MonitorFieldRules
{
    public const int MIN_TIMEOUT_SECONDS = 1;

    public const int NAME_MIN_LENGTH = 1;

    public const bool DEFAULT_FOLLOW_REDIRECTS = true;

    public function __construct(private MonitoringSettings $settings) {}

    public function rules(bool $partial): array
    {
        $presence = $partial ? 'sometimes' : 'required';
        $limits = $this->settings->limits;

        return [
            'name' => [$presence, 'string', 'min:'.self::NAME_MIN_LENGTH, 'max:'.$limits->nameMaxLength],
            'url' => [$presence, 'string', 'max:'.$limits->urlMaxLength, new ValidMonitorUrl($this->settings->probe->allowPrivateTargets)],
            'method' => ['sometimes', 'string', Rule::enum(HttpMethod::class)],
            'interval_seconds' => ['sometimes', 'integer', Rule::in($this->settings->intervals->allowedSeconds)],
            'timeout_seconds' => ['sometimes', 'integer', 'min:'.self::MIN_TIMEOUT_SECONDS, 'max:'.$this->settings->probe->maxTimeoutSeconds],
            'accepted_status_codes' => ['sometimes', 'array', 'list', 'min:1', 'max:'.$limits->maxAcceptedStatusRanges],
            'accepted_status_codes.*' => ['string', new ValidStatusCodeRange],
            'follow_redirects' => ['sometimes', 'boolean'],
        ];
    }
}
