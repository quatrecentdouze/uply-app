<?php

declare(strict_types=1);

namespace App\Shared\Errors;

use RuntimeException;

final class DomainError extends RuntimeException
{
    public function __construct(
        public readonly ErrorCode $error,
        public readonly array $parameters = [],
        public readonly array $headers = [],
    ) {
        parent::__construct($error->code());
    }
}
