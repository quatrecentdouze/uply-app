<?php

declare(strict_types=1);

use App\Shared\Settings\InvalidSettingException;
use App\StatusPages\Settings\StatusPagesSettingsParser;

function shippedStatusPagesConfig(): array
{
    return require dirname(__DIR__, 3).'/config/status_pages.php';
}

it('parses the shipped status pages file', function (): void {
    $settings = (new StatusPagesSettingsParser)->parse('config/status_pages.php', shippedStatusPagesConfig());

    expect($settings->limits->maxPagesPerOrganization)->toBe(10)
        ->and($settings->limits->maxMonitorsPerPage)->toBe(50)
        ->and($settings->lengths->titleMax)->toBe(80)
        ->and($settings->lengths->descriptionMax)->toBe(500)
        ->and($settings->lengths->displayNameMax)->toBe(80)
        ->and($settings->lengths->slugMin)->toBe(3)
        ->and($settings->lengths->slugMax)->toBe(48)
        ->and($settings->public->cacheSeconds)->toBe(30)
        ->and($settings->public->barDays)->toBe(90)
        ->and($settings->public->incidentDays)->toBe(14)
        ->and($settings->public->rateLimitPerMinute)->toBe(60);
});

it('names the file and the key of a wrong value', function (array $override, string $key): void {
    expect(fn () => (new StatusPagesSettingsParser)->parse('config/status_pages.php', array_replace_recursive(shippedStatusPagesConfig(), $override)))
        ->toThrow(InvalidSettingException::class, 'config/status_pages.php')
        ->toThrow(InvalidSettingException::class, $key);
})->with([
    'no page' => [['limits' => ['max_pages_per_organization' => 0]], 'limits.max_pages_per_organization'],
    'monitors as text' => [['limits' => ['max_monitors_per_page' => 'fifty']], 'limits.max_monitors_per_page'],
    'slug bounds crossed' => [['lengths' => ['slug_min' => 50]], 'lengths.slug_min'],
    'negative cache' => [['public' => ['cache_seconds' => -1]], 'public.cache_seconds'],
    'no bar' => [['public' => ['bar_days' => 0]], 'public.bar_days'],
    'no rate' => [['public' => ['rate_limit_per_minute' => 0]], 'public.rate_limit_per_minute'],
]);
