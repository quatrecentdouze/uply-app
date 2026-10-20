<?php

declare(strict_types=1);

use App\Monitoring\Rules\CertificateExpiryRules;
use App\Monitoring\Rules\CertificateInfoRules;
use App\Monitoring\Values\CertificateWatch;
use Carbon\CarbonImmutable;

const EXPIRY_THRESHOLDS = [14, 7, 1];

function certificateMoment(string $moment): CarbonImmutable
{
    return CarbonImmutable::parse($moment, 'UTC');
}

function watchAt(string $now, ?int $alertedDays, ?string $knownExpiry = '2027-03-31 12:00:00', string $seenExpiry = '2027-03-31 12:00:00'): CertificateWatch
{
    return CertificateExpiryRules::watch(
        $knownExpiry === null ? null : certificateMoment($knownExpiry),
        $alertedDays,
        certificateMoment($seenExpiry),
        certificateMoment($now),
        EXPIRY_THRESHOLDS,
    );
}

it('reads the end of the leaf certificate from the pem that curl gives', function (): void {
    $pem = (string) file_get_contents(dirname(__DIR__, 2).'/Support/certificate.pem');
    $chain = [['Cert' => $pem, 'Expire date' => 'Jan 1 00:00:00 2030 GMT'], ['Cert' => '', 'Expire date' => 'Jan 1 00:00:00 2040 GMT']];

    expect(CertificateInfoRules::leafExpiry($chain)?->toIso8601ZuluString())->toBe('2036-10-26T07:37:24Z');
});

it('falls back on the expire date text of curl', function (string $text, string $expected): void {
    expect(CertificateInfoRules::leafExpiry([['Expire date' => $text]])?->toIso8601ZuluString())->toBe($expected);
})->with([
    'openssl' => ['Dec 25 22:56:35 2026 GMT', '2026-12-25T22:56:35Z'],
    'day under ten' => ['Jan  5 01:02:03 2027 GMT', '2027-01-05T01:02:03Z'],
    'iso like' => ['2027-01-05 01:02:03 GMT', '2027-01-05T01:02:03Z'],
]);

it('reads nothing without a certificate', function (mixed $certificates): void {
    expect(CertificateInfoRules::leafExpiry($certificates))->toBeNull();
})->with([
    'no certinfo' => [null],
    'empty chain' => [[]],
    'not a pem' => [[['Cert' => 'not a certificate']]],
    'unknown date' => [[['Expire date' => 'someday']]],
]);

it('stays quiet while the end is further than every threshold', function (): void {
    expect(watchAt('2027-03-01 12:00:00', null))->toEqual(new CertificateWatch(certificateMoment('2027-03-31 12:00:00'), null, null));
});

it('alerts once at each threshold reached', function (string $now, ?int $alertedDays, ?int $alertDays, ?int $stored): void {
    $watch = watchAt($now, $alertedDays);

    expect($watch->alertDays)->toBe($alertDays)
        ->and($watch->alertedDays)->toBe($stored);
})->with([
    'fourteen days exactly' => ['2027-03-17 12:00:00', null, 14, 14],
    'thirteen days after fourteen' => ['2027-03-18 12:00:00', 14, null, 14],
    'seven days after fourteen' => ['2027-03-24 12:00:00', 14, 7, 7],
    'six days after seven' => ['2027-03-25 12:00:00', 7, null, 7],
    'one day after seven' => ['2027-03-30 12:00:00', 7, 1, 1],
    'half a day after one' => ['2027-03-31 00:00:00', 1, null, 1],
    'half a day never alerted' => ['2027-03-31 00:00:00', null, 1, 1],
]);

it('sends one alert, not two, for a certificate first seen five days before its end', function (): void {
    $watch = watchAt('2027-03-26 12:00:00', null, knownExpiry: null);

    expect($watch->alertDays)->toBe(7)
        ->and($watch->alertedDays)->toBe(7);
});

it('sends nothing past the end of the certificate', function (): void {
    expect(watchAt('2027-04-01 12:00:00', null)->alertDays)->toBeNull()
        ->and(watchAt('2027-03-31 12:00:00', 1)->alertDays)->toBeNull();
});

it('starts again from nothing for a renewed certificate', function (): void {
    $renewed = watchAt('2027-03-31 06:00:00', 1, seenExpiry: '2027-06-29 12:00:00');
    $laterOn = watchAt('2027-06-15 12:00:00', $renewed->alertedDays, '2027-06-29 12:00:00', '2027-06-29 12:00:00');

    expect($renewed->alertedDays)->toBeNull()
        ->and($renewed->alertDays)->toBeNull()
        ->and($renewed->expiresAt->toIso8601ZuluString())->toBe('2027-06-29T12:00:00Z')
        ->and($laterOn->alertDays)->toBe(14);
});
