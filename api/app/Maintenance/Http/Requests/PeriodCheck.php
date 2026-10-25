<?php

declare(strict_types=1);

namespace App\Maintenance\Http\Requests;

use App\Maintenance\Enums\PeriodProblem;
use App\Maintenance\Rules\MaintenanceRules;
use App\Maintenance\Settings\WindowBounds;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Validator;

final class PeriodCheck
{
    private const string MESSAGE_PREFIX = 'maintenance.validation.';

    public static function report(Validator $validator, CarbonImmutable $startsAt, CarbonImmutable $endsAt, CarbonImmutable $now, bool $checksStart, WindowBounds $bounds): void
    {
        $problems = MaintenanceRules::periodProblems($startsAt, $endsAt, $now, $checksStart, $bounds->startGraceSeconds, $bounds->maxDaysAhead, $bounds->maxDurationHours);
        $parameters = ['seconds' => $bounds->startGraceSeconds, 'days' => $bounds->maxDaysAhead, 'hours' => $bounds->maxDurationHours];
        foreach ($problems as $problem) {
            if (! $problem instanceof PeriodProblem) {
                continue;
            }
            $key = self::MESSAGE_PREFIX.$problem->value;
            $text = $validator->getTranslator()->get($key, $parameters);
            $validator->errors()->add($problem->field(), is_string($text) ? $text : $key);
        }
    }
}
