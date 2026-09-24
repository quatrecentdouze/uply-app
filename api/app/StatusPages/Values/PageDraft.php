<?php

declare(strict_types=1);

namespace App\StatusPages\Values;

final readonly class PageDraft
{
    public function __construct(
        public ?string $title,
        public ?string $slug,
        public bool $changesDescription,
        public ?string $description,
        public ?bool $isPublished,
        public ?array $monitors,
    ) {}
}
