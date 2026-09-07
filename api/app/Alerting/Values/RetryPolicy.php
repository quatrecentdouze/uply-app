<?php

declare(strict_types=1);

namespace App\Alerting\Values;

use InvalidArgumentException;

final readonly class RetryPolicy
{
    public function __construct(
        public int $maxAttempts,
        public array $backoffSeconds,
    ) {
        if ($maxAttempts < 1 || count($backoffSeconds) !== $maxAttempts - 1) {
            throw new InvalidArgumentException('a retry policy needs one delay per retry');
        }
    }

    public function delayAfter(int $attemptsMade): int
    {
        $delay = $this->backoffSeconds[$attemptsMade - 1] ?? null;

        return is_int($delay) ? $delay : 0;
    }
}
