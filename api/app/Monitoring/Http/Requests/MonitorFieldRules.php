<?php

declare(strict_types=1);

namespace App\Monitoring\Http\Requests;

use App\Monitoring\Enums\HttpMethod;
use App\Monitoring\Settings\MonitoringSettings;
use App\Monitoring\Values\RequestHeader;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final readonly class MonitorFieldRules
{
    public const int MIN_TIMEOUT_SECONDS = 1;

    public const int NAME_MIN_LENGTH = 1;

    public const int HEADER_NAME_MIN_LENGTH = 1;

    public const bool DEFAULT_FOLLOW_REDIRECTS = true;

    private const array CONFLICTING_FIELDS = ['request_body'];

    public function __construct(private MonitoringSettings $settings) {}

    public function rules(bool $partial): array
    {
        $presence = $partial ? 'sometimes' : 'required';
        $limits = $this->settings->limits;
        $confirmation = $this->settings->confirmation;
        $request = $this->settings->request;
        $threshold = ['sometimes', 'integer', 'min:'.$confirmation->minThreshold, 'max:'.$confirmation->maxThreshold];

        return [
            'name' => [$presence, 'string', 'min:'.self::NAME_MIN_LENGTH, 'max:'.$limits->nameMaxLength],
            'url' => [$presence, 'string', 'max:'.$limits->urlMaxLength, new ValidMonitorUrl($this->settings->probe->allowPrivateTargets)],
            'method' => ['sometimes', 'string', Rule::enum(HttpMethod::class)],
            'interval_seconds' => ['sometimes', 'integer', Rule::in($this->settings->intervals->allowedSeconds)],
            'timeout_seconds' => ['sometimes', 'integer', 'min:'.self::MIN_TIMEOUT_SECONDS, 'max:'.$this->settings->probe->maxTimeoutSeconds],
            'accepted_status_codes' => ['sometimes', 'array', 'list', 'min:1', 'max:'.$limits->maxAcceptedStatusRanges],
            'accepted_status_codes.*' => ['string', new ValidStatusCodeRange],
            'follow_redirects' => ['sometimes', 'boolean'],
            'failure_threshold' => $threshold,
            'recovery_threshold' => $threshold,
            'request_headers' => ['sometimes', 'array', 'list', 'max:'.$request->maxHeaders],
            'request_headers.*' => ['array:name,value'],
            'request_headers.*.name' => [
                'required',
                'string',
                'min:'.self::HEADER_NAME_MIN_LENGTH,
                'max:'.$request->headerNameMaxLength,
                'regex:'.RequestHeader::NAME_PATTERN,
                'distinct:ignore_case',
                new AllowedHeaderName($request),
            ],
            'request_headers.*.value' => ['required', 'string', 'max:'.$request->headerValueMaxLength, 'regex:'.RequestHeader::VALUE_PATTERN],
            'request_body' => ['sometimes', 'nullable', 'string', 'max:'.$request->bodyMaxLength],
        ];
    }

    public static function headersFrom(mixed $items): array
    {
        $headers = [];
        foreach (is_array($items) ? $items : [] as $item) {
            $name = is_array($item) ? ($item['name'] ?? null) : null;
            $value = is_array($item) ? ($item['value'] ?? null) : null;
            if (is_string($name) && is_string($value)) {
                $headers[] = new RequestHeader($name, $value);
            }
        }

        return $headers;
    }

    public static function bodyFrom(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    public static function sentFields(Request $request): array
    {
        $sent = [];
        foreach (self::CONFLICTING_FIELDS as $field) {
            if ($request->filled($field)) {
                $sent[] = $field;
            }
        }

        return $sent;
    }
}
