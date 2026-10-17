<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use InvalidArgumentException;

final readonly class RequestHeader
{
    public const string NAME_PATTERN = "/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/D";

    public const string VALUE_PATTERN = '/^[^\x00-\x1F\x7F]*$/uD';

    public function __construct(
        public string $name,
        public string $value,
    ) {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new InvalidArgumentException('a request header name must be an http token');
        }
        if (preg_match(self::VALUE_PATTERN, $value) !== 1) {
            throw new InvalidArgumentException('a request header value must be printable text without line breaks');
        }
    }

    public function isNamed(string $name): bool
    {
        return strcasecmp($this->name, $name) === 0;
    }
}
