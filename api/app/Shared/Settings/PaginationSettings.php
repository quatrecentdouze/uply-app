<?php

declare(strict_types=1);

namespace App\Shared\Settings;

use InvalidArgumentException;

final readonly class PaginationSettings
{
    public function __construct(
        public int $defaultPerPage,
        public int $maxPerPage,
    ) {
        if ($defaultPerPage < 1 || $defaultPerPage > $maxPerPage) {
            throw new InvalidArgumentException('the default page size must be between 1 and the max page size');
        }
    }

    public function perPage(?int $requested): int
    {
        return $requested === null ? $this->defaultPerPage : max(1, min($requested, $this->maxPerPage));
    }

    public function perPageRules(): array
    {
        return ['nullable', 'integer', 'min:1', 'max:'.$this->maxPerPage];
    }
}
