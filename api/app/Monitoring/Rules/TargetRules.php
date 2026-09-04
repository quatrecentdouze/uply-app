<?php

declare(strict_types=1);

namespace App\Monitoring\Rules;

use App\Monitoring\Enums\AddressScope;
use App\Monitoring\Enums\FailureKind;
use App\Monitoring\Values\PinnedTarget;
use App\Monitoring\Values\TargetRefusedException;
use App\Monitoring\Values\TargetUrl;

final class TargetRules
{
    private const array DEFAULT_PORTS = ['http' => 80, 'https' => 443];

    private const string HOST_NAME_PATTERN = '/^[a-z0-9_]([a-z0-9_-]*[a-z0-9_])?(\.[a-z0-9_]([a-z0-9_-]*[a-z0-9_])?)*$/';

    public static function parse(string $url): TargetUrl
    {
        $parts = parse_url(trim($url));
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw self::blocked("target {$url} is not an absolute url");
        }
        $scheme = strtolower($parts['scheme']);
        if (! array_key_exists($scheme, self::DEFAULT_PORTS)) {
            throw self::blocked("target scheme {$scheme} is not http or https");
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw self::blocked('target url carries credentials');
        }
        if (isset($parts['port']) && $parts['port'] < 1) {
            throw self::blocked("target port {$parts['port']} is not a valid port");
        }
        $host = self::normalizedHost($parts['host']);
        $hostIsAddress = filter_var($host, FILTER_VALIDATE_IP) !== false;
        if (! $hostIsAddress && preg_match(self::HOST_NAME_PATTERN, $host) !== 1) {
            throw self::blocked("target host {$parts['host']} is not a valid host name");
        }

        return new TargetUrl($url, $scheme, $host, $parts['port'] ?? self::DEFAULT_PORTS[$scheme], $hostIsAddress);
    }

    public static function pin(TargetUrl $target, array $addresses, bool $allowPrivateTargets, ?int $dnsMs): PinnedTarget
    {
        $pinned = null;
        foreach ($addresses as $address) {
            if (! is_string($address)) {
                continue;
            }
            $scope = AddressRules::scope($address);
            if ($scope === AddressScope::Forbidden) {
                throw self::blocked("{$target->host} resolves to the forbidden address {$address}", $dnsMs);
            }
            if ($scope === AddressScope::Private && ! $allowPrivateTargets) {
                throw self::blocked("{$target->host} resolves to the private address {$address}", $dnsMs);
            }
            $isIpv4 = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
            if ($pinned === null || ($isIpv4 && filter_var($pinned, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false)) {
                $pinned = $address;
            }
        }
        if ($pinned === null) {
            throw new TargetRefusedException(FailureKind::Dns, "{$target->host} has no address", $dnsMs);
        }

        return new PinnedTarget($target, $pinned, $dnsMs);
    }

    private static function normalizedHost(string $host): string
    {
        $host = strtolower(rtrim($host, '.'));
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            return substr($host, 1, -1);
        }
        if (preg_match('/[^\x20-\x7e]/', $host) === 1) {
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

            return $ascii === false ? $host : $ascii;
        }

        return $host;
    }

    private static function blocked(string $reason, ?int $dnsMs = null): TargetRefusedException
    {
        return new TargetRefusedException(FailureKind::BlockedTarget, $reason, $dnsMs);
    }
}
