<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use InvalidArgumentException;

final readonly class StatusCodeRange
{
    public const int LOWEST_CODE = 100;

    public const int HIGHEST_CODE = 599;

    public function __construct(
        public int $from,
        public int $to,
    ) {
        if ($from < self::LOWEST_CODE || $to > self::HIGHEST_CODE || $from > $to) {
            throw new InvalidArgumentException(
                "status code range {$from}-{$to} must stay within ".self::LOWEST_CODE.'-'.self::HIGHEST_CODE.' and start before it ends',
            );
        }
    }

    public static function parse(string $text): self
    {
        if (preg_match('/^\s*(\d{3})\s*(?:-\s*(\d{3})\s*)?$/', $text, $matches) !== 1) {
            throw new InvalidArgumentException("status code range \"{$text}\" must look like 200-299 or 204");
        }
        $from = (int) $matches[1];
        $to = isset($matches[2]) ? (int) $matches[2] : $from;

        return new self($from, $to);
    }

    public function contains(int $code): bool
    {
        return $code >= $this->from && $code <= $this->to;
    }

    public function toText(): string
    {
        return $this->from === $this->to ? (string) $this->from : "{$this->from}-{$this->to}";
    }
}
