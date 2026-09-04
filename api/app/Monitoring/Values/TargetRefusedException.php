<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use App\Monitoring\Enums\FailureKind;
use RuntimeException;

final class TargetRefusedException extends RuntimeException
{
    public function __construct(
        public readonly FailureKind $failureKind,
        string $reason,
        public readonly ?int $dnsMs = null,
    ) {
        parent::__construct($reason);
    }
}
