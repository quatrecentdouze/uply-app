<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use InvalidArgumentException;

final readonly class ConfirmationThresholds
{
    public function __construct(
        public int $failuresToConfirmDown,
        public int $successesToConfirmUp,
    ) {
        if ($failuresToConfirmDown < 1 || $successesToConfirmUp < 1) {
            throw new InvalidArgumentException('confirmation thresholds must be at least 1');
        }
    }
}
