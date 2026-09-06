<?php

declare(strict_types=1);

namespace App\Shared\Settings;

final readonly class SettingsReader
{
    public function __construct(
        private string $source,
        private array $values,
    ) {}

    public function integer(string $key, int $minimum): int
    {
        $value = $this->value($key);
        if (! is_int($value) || $value < $minimum) {
            throw $this->invalid($key, "must be an integer of at least {$minimum}");
        }

        return $value;
    }

    public function boolean(string $key): bool
    {
        $value = $this->value($key);
        if (! is_bool($value)) {
            throw $this->invalid($key, 'must be true or false');
        }

        return $value;
    }

    public function text(string $key): string
    {
        $value = $this->value($key);
        if (! is_string($value) || trim($value) === '') {
            throw $this->invalid($key, 'must be a non empty string');
        }

        return $value;
    }

    public function optionalText(string $key): ?string
    {
        $value = $this->value($key);
        if ($value === null) {
            return null;
        }
        if (! is_string($value) || trim($value) === '') {
            throw $this->invalid($key, 'must be a non empty string or null');
        }

        return $value;
    }

    public function integerList(string $key, int $minimum): array
    {
        $value = $this->value($key);
        if (! is_array($value) || ! array_is_list($value) || $value === []) {
            throw $this->invalid($key, 'must be a non empty list');
        }
        foreach ($value as $item) {
            if (! is_int($item) || $item < $minimum) {
                throw $this->invalid($key, "must only hold integers of at least {$minimum}");
            }
        }

        return $value;
    }

    public function textList(string $key): array
    {
        $value = $this->value($key);
        if (! is_array($value) || ! array_is_list($value) || $value === []) {
            throw $this->invalid($key, 'must be a non empty list');
        }
        foreach ($value as $item) {
            if (! is_string($item) || trim($item) === '') {
                throw $this->invalid($key, 'must only hold non empty strings');
            }
        }

        return $value;
    }

    public function invalid(string $key, string $problem): InvalidSettingException
    {
        return new InvalidSettingException($this->source, $key, $problem);
    }

    private function value(string $key): mixed
    {
        $node = $this->values;
        foreach (explode('.', $key) as $segment) {
            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                throw $this->invalid($key, 'is missing');
            }
            $node = $node[$segment];
        }

        return $node;
    }
}
