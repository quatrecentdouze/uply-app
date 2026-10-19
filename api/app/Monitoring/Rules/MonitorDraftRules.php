<?php

declare(strict_types=1);

namespace App\Monitoring\Rules;

use App\Monitoring\Enums\DraftConflict;
use App\Monitoring\Values\MonitorDraft;

final class MonitorDraftRules
{
    public static function conflicts(MonitorDraft $draft): array
    {
        $conflicts = [];
        if ($draft->request->hasBody() && ! $draft->method->sendsBody()) {
            $conflicts[] = DraftConflict::BodyWithoutBodyMethod;
        }
        if ($draft->keyword !== null && ! $draft->method->receivesBody()) {
            $conflicts[] = DraftConflict::KeywordWithoutBody;
        }

        return $conflicts;
    }
}
