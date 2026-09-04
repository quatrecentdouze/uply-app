<?php

declare(strict_types=1);

namespace App\Monitoring\Rules;

use App\Monitoring\Enums\AddressScope;

final class AddressRules
{
    private const array FORBIDDEN_RANGES = [
        '0.0.0.0/8',
        '169.254.169.254/32',
        '169.254.170.2/32',
        '100.100.100.200/32',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '240.0.0.0/4',
        '::/96',
        '100::/64',
        '2001::/23',
        '2001:db8::/32',
        '64:ff9b:1::/48',
        'fd00:ec2::254/128',
        'ff00::/8',
    ];

    private const array PRIVATE_RANGES = [
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.168.0.0/16',
        'fc00::/7',
        'fe80::/10',
    ];

    private const array EMBEDDED_IPV4_RANGES = [
        '::ffff:0:0/96' => 12,
        '64:ff9b::/96' => 12,
        '2002::/16' => 2,
    ];

    private const string IPV6_LOOPBACK = '::1/128';

    private const int IPV4_BYTES = 4;

    private const int BITS_PER_BYTE = 8;

    private const int FULL_BYTE = 0xFF;

    public static function scope(string $address): AddressScope
    {
        $binary = inet_pton(trim($address, '[]'));
        if ($binary === false) {
            return AddressScope::Forbidden;
        }
        $embedded = self::embeddedIpv4($binary);
        if ($embedded !== null) {
            return self::scope($embedded);
        }
        if (self::inRange($binary, self::IPV6_LOOPBACK)) {
            return AddressScope::Private;
        }
        if (self::inAnyRange($binary, self::FORBIDDEN_RANGES)) {
            return AddressScope::Forbidden;
        }
        if (self::inAnyRange($binary, self::PRIVATE_RANGES)) {
            return AddressScope::Private;
        }

        return AddressScope::Public;
    }

    private static function embeddedIpv4(string $binary): ?string
    {
        foreach (self::EMBEDDED_IPV4_RANGES as $range => $offset) {
            if (self::inRange($binary, $range)) {
                $ipv4 = inet_ntop(substr($binary, $offset, self::IPV4_BYTES));

                return $ipv4 === false ? null : $ipv4;
            }
        }

        return null;
    }

    private static function inAnyRange(string $binary, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if (is_string($range) && self::inRange($binary, $range)) {
                return true;
            }
        }

        return false;
    }

    private static function inRange(string $binary, string $range): bool
    {
        [$network, $prefix] = explode('/', $range);
        $networkBinary = inet_pton($network);
        if ($networkBinary === false || strlen($networkBinary) !== strlen($binary)) {
            return false;
        }
        $prefixBits = (int) $prefix;
        $fullBytes = intdiv($prefixBits, self::BITS_PER_BYTE);
        $remainingBits = $prefixBits % self::BITS_PER_BYTE;
        if (strncmp($binary, $networkBinary, $fullBytes) !== 0) {
            return false;
        }
        if ($remainingBits === 0) {
            return true;
        }
        $mask = (self::FULL_BYTE << (self::BITS_PER_BYTE - $remainingBits)) & self::FULL_BYTE;

        return (ord($binary[$fullBytes]) & $mask) === (ord($networkBinary[$fullBytes]) & $mask);
    }
}
