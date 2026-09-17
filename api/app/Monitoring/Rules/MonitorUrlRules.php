<?php

declare(strict_types=1);

namespace App\Monitoring\Rules;

use App\Monitoring\Enums\AddressScope;
use App\Monitoring\Enums\UrlProblem;
use App\Monitoring\Values\TargetRefusedException;

final class MonitorUrlRules
{
    private const array SCHEMES = ['http', 'https'];

    private const string SCHEME_PATTERN = '~^([a-z][a-z0-9+.-]*)://~i';

    public static function problem(string $url, bool $allowPrivateTargets): ?UrlProblem
    {
        if (preg_match(self::SCHEME_PATTERN, trim($url), $matches) !== 1 || ! in_array(strtolower($matches[1]), self::SCHEMES, true)) {
            return UrlProblem::Scheme;
        }
        try {
            $target = TargetRules::parse($url);
        } catch (TargetRefusedException) {
            return UrlProblem::Malformed;
        }
        if (! $target->hostIsAddress) {
            return null;
        }

        return match (AddressRules::scope($target->host)) {
            AddressScope::Public => null,
            AddressScope::Private => $allowPrivateTargets ? null : UrlProblem::BlockedAddress,
            AddressScope::Forbidden => UrlProblem::BlockedAddress,
        };
    }
}
