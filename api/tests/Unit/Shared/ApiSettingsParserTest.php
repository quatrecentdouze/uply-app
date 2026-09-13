<?php

declare(strict_types=1);

use App\Shared\Settings\ApiSettingsParser;
use App\Shared\Settings\InvalidSettingException;

function shippedApiConfig(): array
{
    return require dirname(__DIR__, 3).'/config/api.php';
}

it('parses the shipped api file', function (): void {
    $settings = (new ApiSettingsParser)->parse('config/api.php', shippedApiConfig());

    expect($settings->rateLimitPerMinute)->toBe(120)
        ->and($settings->metaCacheSeconds)->toBe(300)
        ->and($settings->pagination->defaultPerPage)->toBe(25)
        ->and($settings->pagination->maxPerPage)->toBe(100);
});

it('picks the page size within the bounds', function (?int $requested, int $expected): void {
    $pagination = (new ApiSettingsParser)->parse('config/api.php', shippedApiConfig())->pagination;

    expect($pagination->perPage($requested))->toBe($expected);
})->with([
    'default' => [null, 25],
    'asked' => [50, 50],
    'above the max' => [500, 100],
    'below one' => [0, 1],
]);

it('names the file and the key of a wrong value', function (array $override, string $key): void {
    expect(fn () => (new ApiSettingsParser)->parse('config/api.php', array_replace_recursive(shippedApiConfig(), $override)))
        ->toThrow(InvalidSettingException::class, 'config/api.php')
        ->toThrow(InvalidSettingException::class, $key);
})->with([
    'rate limit' => [['rate_limit_per_minute' => 0], 'rate_limit_per_minute'],
    'meta cache' => [['meta_cache_seconds' => -1], 'meta_cache_seconds'],
    'default above max' => [['pagination' => ['default_per_page' => 200]], 'pagination.default_per_page'],
    'max' => [['pagination' => ['max_per_page' => 'many']], 'pagination.max_per_page'],
]);
