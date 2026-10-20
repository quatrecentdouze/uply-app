<?php

declare(strict_types=1);

namespace App\Monitoring\Rules;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;

final class CertificateInfoRules
{
    private const string PEM = 'Cert';

    private const string EXPIRE_DATE = 'Expire date';

    private const string VALID_TO = 'validTo_time_t';

    private const array EXPIRE_DATE_FORMATS = ['M j H:i:s Y T', 'Y-m-d H:i:s T'];

    public static function leafExpiry(mixed $certificates): ?CarbonImmutable
    {
        $leaf = is_array($certificates) ? ($certificates[0] ?? null) : null;
        if (! is_array($leaf)) {
            return null;
        }

        return self::fromPem($leaf[self::PEM] ?? null) ?? self::fromText($leaf[self::EXPIRE_DATE] ?? null);
    }

    private static function fromPem(mixed $pem): ?CarbonImmutable
    {
        if (! is_string($pem) || $pem === '') {
            return null;
        }
        $parsed = openssl_x509_parse($pem);
        $validTo = is_array($parsed) ? ($parsed[self::VALID_TO] ?? null) : null;

        return is_int($validTo) ? CarbonImmutable::createFromTimestampUTC($validTo) : null;
    }

    private static function fromText(mixed $text): ?CarbonImmutable
    {
        if (! is_string($text)) {
            return null;
        }
        $normalized = (string) preg_replace('/\s+/', ' ', trim($text));
        foreach (self::EXPIRE_DATE_FORMATS as $format) {
            $moment = DateTimeImmutable::createFromFormat("!{$format}", $normalized, new DateTimeZone('UTC'));
            if ($moment !== false) {
                return CarbonImmutable::createFromTimestampUTC($moment->getTimestamp());
            }
        }

        return null;
    }
}
