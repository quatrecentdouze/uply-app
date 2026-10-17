<?php

declare(strict_types=1);

namespace App\Monitoring\Enums;

enum HttpMethod: string
{
    case Get = 'get';
    case Head = 'head';
    case Post = 'post';
    case Put = 'put';
    case Patch = 'patch';

    public function verb(): string
    {
        return strtoupper($this->value);
    }

    public function sendsBody(): bool
    {
        return match ($this) {
            self::Post, self::Put, self::Patch => true,
            self::Get, self::Head => false,
        };
    }

    public function receivesBody(): bool
    {
        return $this !== self::Head;
    }

    public static function bodyMethods(): array
    {
        return array_values(array_filter(self::cases(), fn (self $method): bool => $method->sendsBody()));
    }
}
