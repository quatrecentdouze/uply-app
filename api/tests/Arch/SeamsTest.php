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
$productFamilies = array_map(fn (string $family): string => "App\\{$family}", array_diff(array_keys($seams), ['Shared']));
$globals = ['Illuminate\Support\Facades', 'now', 'today', 'config', 'app', 'resolve', 'request', 'auth', 'env', '__', 'trans', 'event', 'dispatch', 'logger', 'cache'];
$clockAndChance = ['Illuminate', 'App\Shared\Clock', 'Carbon\Carbon', 'time', 'microtime', 'hrtime', 'date', 'mktime', 'strtotime', 'rand', 'random_int'];

foreach ($seams as $family => $allowed) {
    arch("{$family} reaches other families only through its listed seams")
        ->expect("App\\{$family}")
        ->not->toUse(array_values(array_diff($productFamilies, ["App\\{$family}"])))
        ->ignoring(array_map(fn (string $seam): string => "App\\{$seam}", $allowed));

    arch("{$family} uses no facade or global helper")
        ->expect("App\\{$family}")
        ->not->toUse($globals);

    arch("{$family} rules stay pure")
        ->expect("App\\{$family}\\Rules")
        ->not->toUse($clockAndChance);

    foreach (['Values', 'Enums', 'Settings'] as $layer) {
        arch("{$family} {$layer} know no framework")
            ->expect("App\\{$family}\\{$layer}")
            ->not->toUse('Illuminate');
    }

    foreach (['Events', 'Contracts'] as $layer) {
        arch("{$family} {$layer} publish no plumbing")
            ->expect("App\\{$family}\\{$layer}")
            ->not->toUse(['Illuminate\Database', 'Illuminate\Http', "App\\{$family}\\Models", "App\\{$family}\\Storage", "App\\{$family}\\Jobs", "App\\{$family}\\Console"]);
    }
}

it('watches every family folder of app', function () use ($seams): void {
    $folders = array_map('basename', glob(dirname(__DIR__, 2).'/app/*', GLOB_ONLYDIR) ?: []);

    expect(array_values(array_diff($folders, ['Http', 'Providers'])))->toEqualCanonicalizing(array_keys($seams));
});

arch('only the http probe and the webhook senders know the http client')
    ->expect('App')
    ->not->toUse(['Illuminate\Http\Client', 'GuzzleHttp', 'Psr\Http'])
    ->ignoring(['App\Monitoring\Probe\HttpProbe', 'App\Alerting\Senders\DiscordSender', 'App\Alerting\Senders\SlackSender']);

arch('only the mail sender knows the mail transport')
    ->expect('App')
    ->not->toUse(['Illuminate\Contracts\Mail', 'Symfony\Component\Mailer'])
    ->ignoring('App\Alerting\Senders\MailSender');

it('keeps the clock out of every rules class', function (): void {
    $rulesFiles = glob(dirname(__DIR__, 2).'/app/*/Rules/*.php') ?: [];

    foreach ($rulesFiles as $file) {
        expect(file_get_contents($file))->not->toMatch('/::(now|today|tomorrow|yesterday)\s*\(/');
    }
    expect($rulesFiles)->not->toBeEmpty();
});

arch('php preset')->preset()->php();

arch('security preset')->preset()->security();

foreach (['App', 'Database\Factories', 'Database\Seeders', 'Tests'] as $namespace) {
    arch("{$namespace} declares strict types")
        ->expect($namespace)
        ->toUseStrictTypes();
}
