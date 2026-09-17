<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

function readmeCommandLines(): array
{
    $readme = (string) file_get_contents(base_path('../README.md'));
    preg_match_all('/```bash\n(.*?)```/s', $readme, $blocks);

    return array_values(array_filter(array_map('trim', explode("\n", implode("\n", $blocks[1])))));
}

it('cites only composer scripts that exist', function (): void {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);
    $scripts = array_keys($composer['scripts']);
    $cited = [];
    foreach (readmeCommandLines() as $line) {
        if (preg_match('/^composer ([a-z:-]+)/', $line, $match) === 1 && $match[1] !== 'install') {
            $cited[] = $match[1];
        }
    }

    expect($cited)->not->toBeEmpty();
    foreach ($cited as $script) {
        expect($scripts)->toContain($script);
    }
});

it('cites only artisan commands that exist', function (): void {
    $commands = array_keys(Artisan::all());
    $cited = [];
    foreach (readmeCommandLines() as $line) {
        if (preg_match('/^php artisan ([a-z:-]+)/', $line, $match) === 1) {
            $cited[] = $match[1];
        }
    }

    expect($cited)->toContain('checks:dispatch')->toContain('monitoring:maintain')->toContain('scramble:export');
    foreach ($cited as $command) {
        expect($commands)->toContain($command);
    }
});
