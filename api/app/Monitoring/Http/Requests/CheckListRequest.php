<?php

declare(strict_types=1);

namespace App\Monitoring\Http\Requests;

use App\Monitoring\Rules\CheckStorageRules;
use App\Monitoring\Settings\MonitoringSettings;
use App\Shared\Clock;
use App\Shared\Settings\PaginationSettings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class CheckListRequest extends FormRequest
{
    private const int CURSOR_MAX_LENGTH = 512;

    public function rules(PaginationSettings $pagination, MonitoringSettings $settings, Clock $clock): array
    {
        $oldest = $this->oldest($settings, $clock)->toIso8601ZuluString();

        return [
            'from' => ['nullable', 'date', 'after_or_equal:'.$oldest],
            'to' => ['nullable', 'date', 'after_or_equal:'.$oldest, 'after_or_equal:from'],
            'cursor' => ['nullable', 'string', 'max:'.self::CURSOR_MAX_LENGTH],
            'per_page' => $pagination->perPageRules(),
        ];
    }

    public function from(MonitoringSettings $settings, Clock $clock): CarbonImmutable
    {
        $from = $this->validated('from');

        return is_string($from) ? CarbonImmutable::parse($from)->utc() : $this->oldest($settings, $clock);
    }

    public function to(Clock $clock): CarbonImmutable
    {
        $to = $this->validated('to');

        return is_string($to) ? CarbonImmutable::parse($to)->utc() : $clock->now();
    }

    public function perPage(PaginationSettings $pagination): int
    {
        $requested = $this->validated('per_page');

        return $pagination->perPage(is_numeric($requested) ? (int) $requested : null);
    }

    private function oldest(MonitoringSettings $settings, Clock $clock): CarbonImmutable
    {
        return CheckStorageRules::oldestRawResult($clock->now(), $settings->retention->rawResultsDays);
    }
}
