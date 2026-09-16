<?php

declare(strict_types=1);

namespace App\Shared\Meta;

use App\Shared\Settings\ApiSettings;

final readonly class ApiMetaSection implements MetaSection
{
    public function __construct(private ApiSettings $settings) {}

    public function key(): string
    {
        return 'api';
    }

    public function describe(): array
    {
        return [
            'pagination' => [
                'default_per_page' => $this->settings->pagination->defaultPerPage,
                'max_per_page' => $this->settings->pagination->maxPerPage,
            ],
            'rate_limit_per_minute' => $this->settings->rateLimitPerMinute,
        ];
    }
}
