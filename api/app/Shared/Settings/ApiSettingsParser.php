<?php

declare(strict_types=1);

namespace App\Shared\Settings;

final class ApiSettingsParser
{
    public function parse(string $source, array $values): ApiSettings
    {
        $reader = new SettingsReader($source, $values);
        $maxPerPage = $reader->integer('pagination.max_per_page', 1);
        $defaultPerPage = $reader->integer('pagination.default_per_page', 1);
        if ($defaultPerPage > $maxPerPage) {
            throw $reader->invalid('pagination.default_per_page', 'must not exceed pagination.max_per_page');
        }

        return new ApiSettings(
            rateLimitPerMinute: $reader->integer('rate_limit_per_minute', 1),
            metaCacheSeconds: $reader->integer('meta_cache_seconds', 0),
            pagination: new PaginationSettings($defaultPerPage, $maxPerPage),
        );
    }
}
