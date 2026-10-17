<?php

declare(strict_types=1);

namespace App\Monitoring\Values;

use InvalidArgumentException;

final readonly class MonitorRequest
{
    private const string HEADERS = 'headers';

    private const string BODY = 'body';

    private const string NAME = 'name';

    private const string VALUE = 'value';

    public array $headers;

    public ?string $body;

    public function __construct(array $headers, ?string $body)
    {
        $kept = [];
        $names = [];
        foreach ($headers as $header) {
            if (! $header instanceof RequestHeader) {
                throw new InvalidArgumentException('a monitor request only holds request headers');
            }
            $name = strtolower($header->name);
            if (in_array($name, $names, true)) {
                throw new InvalidArgumentException('request header names must be unique ignoring case');
            }
            $names[] = $name;
            $kept[] = $header;
        }
        $this->headers = $kept;
        $this->body = $body === '' ? null : $body;
    }

    public static function none(): self
    {
        return new self([], null);
    }

    public static function fromStored(?array $stored): self
    {
        if ($stored === null) {
            return self::none();
        }
        $headers = [];
        foreach (is_array($stored[self::HEADERS] ?? null) ? $stored[self::HEADERS] : [] as $header) {
            $name = is_array($header) ? ($header[self::NAME] ?? null) : null;
            $value = is_array($header) ? ($header[self::VALUE] ?? null) : null;
            if (! is_string($name) || ! is_string($value)) {
                throw new InvalidArgumentException('a stored request header needs a name and a value');
            }
            $headers[] = new RequestHeader($name, $value);
        }
        $body = $stored[self::BODY] ?? null;

        return new self($headers, is_string($body) ? $body : null);
    }

    public function toStored(): ?array
    {
        if ($this->isEmpty()) {
            return null;
        }

        return [
            self::HEADERS => array_map(
                fn (RequestHeader $header): array => [self::NAME => $header->name, self::VALUE => $header->value],
                $this->headers,
            ),
            self::BODY => $this->body,
        ];
    }

    public function withHeaders(array $headers): self
    {
        return new self($headers, $this->body);
    }

    public function withBody(?string $body): self
    {
        return new self($this->headers, $body);
    }

    public function headerNames(): array
    {
        return array_map(fn (RequestHeader $header): string => $header->name, $this->headers);
    }

    public function hasBody(): bool
    {
        return $this->body !== null;
    }

    public function isEmpty(): bool
    {
        return $this->headers === [] && $this->body === null;
    }

    public function sameAs(self $other): bool
    {
        return $this->toStored() === $other->toStored();
    }

    public function secrets(): array
    {
        $values = array_map(fn (RequestHeader $header): string => $header->value, $this->headers);

        return array_values(array_filter([...$values, $this->body], fn (?string $secret): bool => $secret !== null && $secret !== ''));
    }
}
