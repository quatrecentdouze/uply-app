<?php

declare(strict_types=1);

use App\Monitoring\Enums\FailureKind;
use App\Monitoring\Probe\DnsTargetGuard;
use App\Monitoring\Settings\ProbeSettings;
use App\Monitoring\Values\TargetRefusedException;
use Tests\Support\StaticHostResolver;

function guardWith(StaticHostResolver $resolver, bool $allowPrivateTargets): DnsTargetGuard
{
    return new DnsTargetGuard($resolver, new ProbeSettings(10, 30, 5, 5, 1024, 'Uply/1.0', $allowPrivateTargets));
}

it('resolves the host and pins a checked address', function (): void {
    $resolver = new StaticHostResolver(['api.example.com' => ['93.184.215.14']]);

    $pinned = guardWith($resolver, false)->pin('https://api.example.com/health');

    expect($pinned->address)->toBe('93.184.215.14')
        ->and($pinned->dnsMs)->toBe(1)
        ->and($resolver->resolvedHosts)->toBe(['api.example.com']);
});

it('skips the resolver for an ip literal', function (): void {
    $resolver = new StaticHostResolver;

    guardWith($resolver, true)->pin('http://127.0.0.1:8090/');

    expect($resolver->resolvedHosts)->toBe([]);
});

it('refuses a name that resolves to a private address while private targets are off', function (): void {
    $guard = guardWith(new StaticHostResolver(['internal.example.com' => ['93.184.215.14', '192.168.1.20']]), false);

    expect(fn () => $guard->pin('https://internal.example.com/'))
        ->toThrow(fn (TargetRefusedException $refusal) => expect($refusal->failureKind)->toBe(FailureKind::BlockedTarget));
});

it('reports an unknown host as a dns failure', function (): void {
    $guard = guardWith(new StaticHostResolver(['gone.example.com' => []]), false);

    expect(fn () => $guard->pin('https://gone.example.com/'))
        ->toThrow(fn (TargetRefusedException $refusal) => expect($refusal->failureKind)->toBe(FailureKind::Dns));
});
