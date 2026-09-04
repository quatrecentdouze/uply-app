<?php

declare(strict_types=1);

use App\Monitoring\Enums\FailureKind;
use App\Monitoring\Rules\TargetRules;
use App\Monitoring\Values\TargetRefusedException;

function refusalOf(Closure $action): TargetRefusedException
{
    try {
        $action();
    } catch (TargetRefusedException $refusal) {
        return $refusal;
    }

    throw new LogicException('the target was not refused');
}

it('parses http and https targets with their ports', function (string $url, string $scheme, string $host, int $port, bool $isAddress): void {
    $target = TargetRules::parse($url);

    expect($target->scheme)->toBe($scheme)
        ->and($target->host)->toBe($host)
        ->and($target->port)->toBe($port)
        ->and($target->hostIsAddress)->toBe($isAddress);
})->with([
    ['https://api.example.com/health', 'https', 'api.example.com', 443, false],
    ['HTTP://API.Example.COM./x', 'http', 'api.example.com', 80, false],
    ['http://127.0.0.1:8090/', 'http', '127.0.0.1', 8090, true],
    ['https://[2606:4700::1111]:8443/', 'https', '2606:4700::1111', 8443, true],
    ['https://bücher.example/', 'https', 'xn--bcher-kva.example', 443, false],
]);

it('refuses what is not a plain http target', function (string $url): void {
    expect(refusalOf(fn () => TargetRules::parse($url))->failureKind)->toBe(FailureKind::BlockedTarget);
})->with([
    'no scheme' => ['api.example.com/health'],
    'ftp' => ['ftp://files.example.com/'],
    'file' => ['file:///etc/passwd'],
    'gopher' => ['gopher://127.0.0.1:6379/_'],
    'credentials' => ['https://admin:secret@api.example.com/'],
    'bad host' => ['https://exa mple.com/'],
    'port zero' => ['http://example.com:0/'],
]);

it('pins the first ipv4 address when every address is public', function (): void {
    $pinned = TargetRules::pin(TargetRules::parse('https://api.example.com/'), ['2606:4700::1111', '93.184.215.14'], false, 3);

    expect($pinned->address)->toBe('93.184.215.14')
        ->and($pinned->dnsMs)->toBe(3);
});

it('refuses a host when one of its addresses is private', function (): void {
    $refusal = refusalOf(fn () => TargetRules::pin(TargetRules::parse('https://mixed.example.com/'), ['93.184.215.14', '10.0.0.5'], false, 4));

    expect($refusal->failureKind)->toBe(FailureKind::BlockedTarget)
        ->and($refusal->getMessage())->toContain('10.0.0.5')
        ->and($refusal->dnsMs)->toBe(4);
});

it('lets private addresses through only when the setting allows them', function (): void {
    expect(TargetRules::pin(TargetRules::parse('http://127.0.0.1/'), ['127.0.0.1'], true, null)->address)->toBe('127.0.0.1');
});

it('refuses metadata addresses even when private targets are allowed', function (): void {
    expect(refusalOf(fn () => TargetRules::pin(TargetRules::parse('http://169.254.169.254/'), ['169.254.169.254'], true, null))->failureKind)
        ->toBe(FailureKind::BlockedTarget);
});

it('reports a host without address as a dns failure', function (): void {
    expect(refusalOf(fn () => TargetRules::pin(TargetRules::parse('https://nowhere.example.com/'), [], false, 12))->failureKind)
        ->toBe(FailureKind::Dns);
});
