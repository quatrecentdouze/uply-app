<?php

declare(strict_types=1);

namespace App\Shared\Errors;

interface ErrorCode
{
    public function code(): string;

    public function httpStatus(): int;

    public function messageKey(): string;
}
