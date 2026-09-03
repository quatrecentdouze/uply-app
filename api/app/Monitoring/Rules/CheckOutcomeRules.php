<?php

declare(strict_types=1);

namespace App\Monitoring\Rules;

use App\Monitoring\Enums\FailureKind;
use App\Monitoring\Values\AcceptedStatusCodes;
use App\Monitoring\Values\CheckVerdict;
use App\Monitoring\Values\ProbeResult;

final class CheckOutcomeRules
{
    public static function evaluate(AcceptedStatusCodes $acceptedStatusCodes, ProbeResult $result): CheckVerdict
    {
        if ($result->failureKind !== null) {
            return CheckVerdict::down($result->failureKind);
        }
        if ($result->httpStatus !== null && $acceptedStatusCodes->accepts($result->httpStatus)) {
            return CheckVerdict::up();
        }

        return CheckVerdict::down(FailureKind::HttpStatus);
    }
}
