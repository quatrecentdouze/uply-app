<?php

declare(strict_types=1);

use App\Monitoring\Enums\HttpMethod;
use App\Monitoring\Rules\DiagnosticRules;
use App\Monitoring\Rules\ProbeHopRules;
use App\Monitoring\Rules\TargetRules;
use App\Monitoring\Values\MonitorRequest;
use App\Monitoring\Values\ProbeHop;
use App\Monitoring\Values\RequestHeader;

function hookRequest(): MonitorRequest
{
    return new MonitorRequest([new RequestHeader('Authorization', 'Bearer hop-token'), new RequestHeader('user-agent', 'Custom/2.0')], '{"a":1}');
}

function followedHop(HttpMethod $method, int $status, string $from = 'https://api.example.com/a', string $to = 'https://api.example.com/b'): ProbeHop
{
    return ProbeHopRules::follow(ProbeHop::first($method, hookRequest()), $status, TargetRules::parse($from), TargetRules::parse($to));
}

it('sends the body on the first hop only with a body method', function (HttpMethod $method, bool $sendsBody): void {
    expect(ProbeHop::first($method, hookRequest())->sendsBody)->toBe($sendsBody)
        ->and(ProbeHop::first($method, new MonitorRequest([], null))->sendsBody)->toBeFalse();
})->with([[HttpMethod::Get, false], [HttpMethod::Head, false], [HttpMethod::Post, true], [HttpMethod::Put, true], [HttpMethod::Patch, true]]);

it('turns a body method into a get without body on 301, 302 and 303', function (int $status, HttpMethod $method): void {
    $hop = followedHop($method, $status);

    expect($hop->method)->toBe(HttpMethod::Get)
        ->and($hop->sendsBody)->toBeFalse()
        ->and($hop->sendsUserHeaders)->toBeTrue();
})->with([301, 302, 303])->with([HttpMethod::Post, HttpMethod::Put, HttpMethod::Patch]);

it('keeps get and head as they are on any redirect', function (int $status, HttpMethod $method): void {
    expect(followedHop($method, $status)->method)->toBe($method);
})->with([301, 302, 303, 307, 308])->with([HttpMethod::Get, HttpMethod::Head]);

it('keeps the method and the body on 307 and 308', function (int $status): void {
    $hop = followedHop(HttpMethod::Patch, $status);

    expect($hop->method)->toBe(HttpMethod::Patch)
        ->and($hop->sendsBody)->toBeTrue()
        ->and($hop->sendsUserHeaders)->toBeTrue();
})->with([307, 308]);

it('drops the headers and the body on a hop to another origin, and never takes them back', function (string $to): void {
    $away = followedHop(HttpMethod::Post, 307, to: $to);
    $back = ProbeHopRules::follow($away, 307, TargetRules::parse($to), TargetRules::parse('https://api.example.com/c'));

    expect($away->method)->toBe(HttpMethod::Post)
        ->and($away->sendsBody)->toBeFalse()
        ->and($away->sendsUserHeaders)->toBeFalse()
        ->and($back->sendsBody)->toBeFalse()
        ->and($back->sendsUserHeaders)->toBeFalse();
})->with([
    'other host' => ['https://other.example.com/b'],
    'other scheme' => ['http://api.example.com/b'],
    'other port' => ['https://api.example.com:8443/b'],
]);

it('sees the same origin through the case of the host and the default port', function (): void {
    expect(ProbeHopRules::sameOrigin(TargetRules::parse('https://API.example.com/a'), TargetRules::parse('https://api.example.com:443/b')))->toBeTrue();
});

it('lets a header of the monitor replace the user agent of the probe, ignoring case', function (): void {
    expect(ProbeHopRules::headers(ProbeHop::first(HttpMethod::Get, hookRequest()), hookRequest(), 'Uply/1.0'))->toBe([
        'Authorization' => 'Bearer hop-token',
        'user-agent' => 'Custom/2.0',
    ]);
});

it('sends only the user agent of the probe once the headers are dropped', function (): void {
    expect(ProbeHopRules::headers(new ProbeHop(HttpMethod::Get, false, false), hookRequest(), 'Uply/1.0'))->toBe(['User-Agent' => 'Uply/1.0']);
});

it('hides every secret of a diagnostic, the longest first', function (): void {
    $message = 'failed with Bearer hop-token and {"a":"Bearer hop-token"}';

    expect(DiagnosticRules::withoutSecrets($message, ['Bearer hop-token', '{"a":"Bearer hop-token"}', '']))->toBe('failed with [secret] and [secret]')
        ->and(DiagnosticRules::withoutSecrets('nothing secret', []))->toBe('nothing secret');
});
