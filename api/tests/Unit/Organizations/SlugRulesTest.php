<?php

declare(strict_types=1);

use App\Organizations\Rules\SlugRules;

it('fits a slug to the allowed shape', function (string $slug, string $expected): void {
    expect(SlugRules::fit($slug, 3, 48))->toBe($expected);
})->with([
    'plain' => ['acme-monitoring', 'acme-monitoring'],
    'upper case and spaces' => ['Ops Team', 'ops-team'],
    'stray dashes' => ['--ops--co--', 'ops-co'],
    'too short' => ['ab', 'ab-org'],
    'empty' => ['', 'org'],
    'too long' => [str_repeat('abc-', 20), 'abc-abc-abc-abc-abc-abc-abc-abc-abc-abc-abc-abc'],
]);

it('keeps any input inside the allowed alphabet and length', function (string $slug): void {
    expect(SlugRules::fit($slug, 3, 48))->toMatch('/^[a-z0-9](?:[a-z0-9-]{1,46})[a-z0-9]$/');
})->with(['acme', 'ÅÄÖ', '---', str_repeat('long-name ', 30), '日本語', 'a', 'x_y.z']);

it('takes the first free slug', function (array $taken, string $expected): void {
    expect(SlugRules::firstFree('acme', $taken, 48))->toBe($expected);
})->with([
    'free' => [[], 'acme'],
    'taken once' => [['acme'], 'acme-2'],
    'taken twice' => [['acme', 'acme-2'], 'acme-3'],
    'gap' => [['acme', 'acme-3'], 'acme-2'],
]);

it('shortens the base to fit a suffix', function (): void {
    $base = str_repeat('a', 48);

    expect(SlugRules::firstFree($base, [$base], 48))->toBe(str_repeat('a', 46).'-2');
});
