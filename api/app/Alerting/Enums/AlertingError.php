<?php

declare(strict_types=1);

namespace App\Alerting\Enums;

use App\Shared\Errors\ErrorCode;

enum AlertingError: string implements ErrorCode
{
    case TestLimitReached = 'test_limit_reached';

    public function code(): string
    {
        return $this->value;
    }

    public function httpStatus(): int
    {
        return 429;
    }

    public function messageKey(): string
    {
        return 'alerting.errors.'.$this->value;
    }
}
