<?php

declare(strict_types=1);

namespace App\Monitoring\Http\Requests;

use App\Monitoring\Enums\DraftConflict;
use App\Monitoring\Rules\MonitorDraftRules;
use App\Monitoring\Values\MonitorDraft;
use Illuminate\Validation\Validator;

final class DraftConflictCheck
{
    private const string METHOD_FIELD = 'method';

    public static function report(Validator $validator, MonitorDraft $draft, array $sentFields): void
    {
        foreach (MonitorDraftRules::conflicts($draft) as $conflict) {
            if (! $conflict instanceof DraftConflict) {
                continue;
            }
            $field = in_array($conflict->field(), $sentFields, true) ? $conflict->field() : self::METHOD_FIELD;
            $text = $validator->getTranslator()->get($conflict->messageKey());
            $validator->errors()->add($field, is_string($text) ? $text : $conflict->messageKey());
        }
    }
}
