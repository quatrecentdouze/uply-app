<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use App\Monitoring\Enums\KeywordMatch;
use InvalidArgumentException;

final readonly class Keyword
{
    public function __construct(
        public string $text,
        public KeywordMatch $match,
    ) {
        if ($text === '') {
            throw new InvalidArgumentException('a keyword cannot be empty');
        }
    }
}
