<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use InvalidArgumentException;

final readonly class CheckTotals
{
    public function __construct(
        public int $checksTotal,
        public int $checksUp,
        public int $responseMsSum,
        public int $responseMsCount,
        public ?int $responseMsMin,
        public ?int $responseMsMax,
    ) {
        if ($checksTotal < 0 || $checksUp < 0 || $checksUp > $checksTotal || $responseMsCount < 0) {
            throw new InvalidArgumentException('check totals cannot be negative and up checks stay within the total');
        }
    }

    public static function none(): self
    {
        return new self(0, 0, 0, 0, null, null);
    }
}
