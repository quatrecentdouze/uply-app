<?php

declare(strict_types=1);

namespace App\Shared\Meta;

interface MetaSection
{
    public const string TAG = 'api.meta.sections';

    public function key(): string;

    public function describe(): array;
}
