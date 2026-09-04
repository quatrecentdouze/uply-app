<?php

declare(strict_types=1);

use App\Monitoring\Enums\AddressScope;
use App\Monitoring\Rules\AddressRules;

it('classifies public addresses', function (string $address): void {
    expect(AddressRules::scope($address))->toBe(AddressScope::Public);
})->with([
    '93.184.215.14',
    '1.1.1.1',
    '172.32.0.1',
    '100.128.0.1',
    '2606:4700:4700::1111',
    '2001:4860:4860::8888',
    '::ffff:8.8.8.8',
    '64:ff9b::808:808',
]);

it('classifies private, loopback and link local addresses', function (string $address): void {
    expect(AddressRules::scope($address))->toBe(AddressScope::Private);
})->with([
    '10.0.0.1',
    '172.16.5.4',
    '172.31.255.255',
    '192.168.1.10',
    '100.64.0.1',
    '127.0.0.1',
    '127.255.255.254',
    '169.254.10.20',
    '::1',
    '[::1]',
    'fd12:3456::1',
    'fe80::1',
    '::ffff:127.0.0.1',
    '::ffff:10.1.2.3',
    '2002:c0a8:010a::1',
]);

it('forbids metadata, reserved, documentation and broadcast addresses', function (string $address): void {
    expect(AddressRules::scope($address))->toBe(AddressScope::Forbidden);
})->with([
    '0.0.0.0',
    '0.1.2.3',
    '169.254.169.254',
    '169.254.170.2',
    '100.100.100.200',
    '192.0.2.10',
    '198.51.100.1',
    '203.0.113.7',
    '198.18.0.1',
    '224.0.0.1',
    '239.255.255.250',
    '240.0.0.1',
    '255.255.255.255',
    '::',
    '::7f00:1',
    '2001:db8::1',
    '2001:0:4136:e378:8000:63bf:3fff:fdd2',
    'fd00:ec2::254',
    'ff02::1',
    '::ffff:169.254.169.254',
    'not-an-address',
    '',
]);
