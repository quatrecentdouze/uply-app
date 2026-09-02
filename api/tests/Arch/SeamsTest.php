<?php

declare(strict_types=1);

$seams = [
    'Shared' => [],
    'Organizations' => [],
    'Monitoring' => ['Organizations\Contracts'],
    'Incidents' => ['Organizations\Contracts', 'Monitoring\Events', 'Monitoring\Contracts', 'Alerting\Events'],
    'Alerting' => ['Organizations\Contracts', 'Monitoring\Contracts', 'Incidents\Events'],
    'StatusPages' => ['Organizations\Contracts', 'Monitoring\Contracts', 'Incidents\Contracts'],
    'Realtime' => ['Organizations\Contracts', 'Monitoring\Events', 'Incidents\Events'],
];
$families = array_map(fn (string $family): string => "App\\{$family}", array_keys($seams));
$productFamilies = array_values(array_diff($families, ['App\Shared']));

foreach ($seams as $family => $allowed) {
    arch("{$family} reaches other families only through its listed seams")
        ->expect("App\\{$family}")
        ->not->toUse(array_values(array_diff($productFamilies, ["App\\{$family}"])))
        ->ignoring(array_map(fn (string $seam): string => "App\\{$seam}", $allowed));
}

arch('families use no facade or global helper')
    ->expect($families)
    ->not->toUse(['Illuminate\Support\Facades', 'now', 'today', 'config', 'app', 'resolve', 'request', 'auth', 'env', '__', 'trans', 'event', 'dispatch', 'logger', 'cache']);

foreach (array_keys($seams) as $family) {
    arch("{$family} rules stay pure")
        ->expect("App\\{$family}\\Rules")
        ->not->toUse(['Illuminate', 'App\Shared\Clock', 'Carbon\Carbon', 'time', 'microtime', 'hrtime', 'date', 'mktime', 'strtotime', 'rand', 'random_int']);

    arch("{$family} values, enums and settings know no framework")
        ->expect(["App\\{$family}\\Values", "App\\{$family}\\Enums", "App\\{$family}\\Settings"])
        ->not->toUse('Illuminate');

    arch("{$family} events and contracts publish no plumbing")
        ->expect(["App\\{$family}\\Events", "App\\{$family}\\Contracts"])
        ->not->toUse(['Illuminate\Database', 'Illuminate\Http', "App\\{$family}\\Models", "App\\{$family}\\Storage", "App\\{$family}\\Jobs", "App\\{$family}\\Console"]);
}

it('keeps the clock out of every rules class', function (): void {
    $rulesFiles = glob(dirname(__DIR__, 2).'/app/*/Rules/*.php') ?: [];

    foreach ($rulesFiles as $file) {
        expect(file_get_contents($file))->not->toMatch('/::(now|today|tomorrow|yesterday)\s*\(/');
    }
    expect($rulesFiles)->not->toBeEmpty();
});

arch('php preset')->preset()->php();

arch('security preset')->preset()->security();

arch('strict types everywhere')
    ->expect(['App', 'Database\Factories', 'Database\Seeders', 'Tests'])
    ->toUseStrictTypes();
