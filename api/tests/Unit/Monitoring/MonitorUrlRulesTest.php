<?php

declare(strict_types=1);

use App\Monitoring\Enums\UrlProblem;
use App\Monitoring\Rules\MonitorUrlRules;

it('accepts http and https urls with a host name or a public address', function (string $url): void {
    expect(MonitorUrlRules::problem($url, allowPrivateTargets: false))->toBeNull();
})->with([
    'https://api.example.com/health',
    'HTTP://Example.COM./x?y=1',
    'http://93.184.215.14:8080/',
    'https://[2606:4700:4700::1111]/',
    'https://internal.example.com/admin',
]);

it('refuses other schemes', function (string $url): void {
    expect(MonitorUrlRules::problem($url, allowPrivateTargets: true))->toBe(UrlProblem::Scheme);
})->with([
    'ftp://example.com/file',
    'file:///etc/passwd',
    'gopher://example.com',
    'example.com/health',
    '',
]);

it('refuses urls that are not valid web addresses', function (string $url): void {
    expect(MonitorUrlRules::problem($url, allowPrivateTargets: true))->toBe(UrlProblem::Malformed);
})->with([
    'https://user:secret@example.com/',
    'https://exa mple.com/',
    'https:///nohost',
]);

it('refuses a literal forbidden address in every environment', function (string $url): void {
    expect(MonitorUrlRules::problem($url, allowPrivateTargets: true))->toBe(UrlProblem::BlockedAddress)
        ->and(MonitorUrlRules::problem($url, allowPrivateTargets: false))->toBe(UrlProblem::BlockedAddress);
})->with([
    'http://169.254.169.254/latest/meta-data',
    'http://0.0.0.0/',
    'http://[ff02::1]/',
]);

it('refuses a literal private address unless private targets are allowed', function (string $url): void {
    expect(MonitorUrlRules::problem($url, allowPrivateTargets: false))->toBe(UrlProblem::BlockedAddress)
        ->and(MonitorUrlRules::problem($url, allowPrivateTargets: true))->toBeNull();
})->with([
    'http://127.0.0.1:8090/health',
    'http://10.0.0.8/',
    'http://[::1]/',
    'http://192.168.1.1/',
]);

it('gives every problem a text key', function (UrlProblem $problem): void {
    expect($problem->messageKey())->toBe("monitoring.validation.url.{$problem->value}");
})->with(UrlProblem::cases());
