<?php

declare(strict_types=1);

namespace App\Monitoring\Rules;

use App\Monitoring\Enums\FailureKind;
use App\Monitoring\Enums\KeywordMatch;
use App\Monitoring\Values\AcceptedStatusCodes;
use App\Monitoring\Values\CheckVerdict;
use App\Monitoring\Values\Keyword;
use App\Monitoring\Values\ProbeResult;

final class CheckOutcomeRules
{
    public const string KEYWORD_MISSING = 'the keyword is missing from the response body';

    public const string KEYWORD_FOUND = 'the unwanted keyword is in the response body';

    public static function evaluate(AcceptedStatusCodes $acceptedStatusCodes, ProbeResult $result, ?Keyword $keyword = null): CheckVerdict
    {
        if ($result->failureKind !== null) {
            return CheckVerdict::down($result->failureKind);
        }
        if ($result->httpStatus === null || ! $acceptedStatusCodes->accepts($result->httpStatus)) {
            return CheckVerdict::down(FailureKind::HttpStatus);
        }
        if ($keyword === null) {
            return CheckVerdict::up();
        }
        $found = $result->body !== null && str_contains($result->body, $keyword->text);

        return match (true) {
            $keyword->match === KeywordMatch::Present && ! $found => CheckVerdict::down(FailureKind::KeywordMissing, self::KEYWORD_MISSING),
            $keyword->match === KeywordMatch::Absent && $found => CheckVerdict::down(FailureKind::KeywordFound, self::KEYWORD_FOUND),
            default => CheckVerdict::up(),
        };
    }
}
