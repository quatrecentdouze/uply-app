<?php

declare(strict_types=1);

namespace App\StatusPages\Enums;

use App\Shared\Errors\ErrorCode;

enum StatusPageError: string implements ErrorCode
{
    case StatusPageLimitReached = 'status_page_limit_reached';

    public function code(): string
    {
        return $this->value;
    }

    public function httpStatus(): int
    {
        return 409;
    }

    public function messageKey(): string
    {
        return 'status_pages.errors.'.$this->value;
    }
}
