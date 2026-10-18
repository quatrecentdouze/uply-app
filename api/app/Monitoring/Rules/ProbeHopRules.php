<?php

declare(strict_types=1);

namespace App\Monitoring\Rules;

use App\Monitoring\Enums\HttpMethod;
use App\Monitoring\Values\MonitorRequest;
use App\Monitoring\Values\ProbeHop;
use App\Monitoring\Values\RequestHeader;
use App\Monitoring\Values\TargetUrl;

final class ProbeHopRules
{
    private const array REDIRECTS_TO_GET = [301, 302, 303];

    private const string USER_AGENT = 'User-Agent';

    public static function follow(ProbeHop $hop, int $redirectStatus, TargetUrl $from, TargetUrl $to): ProbeHop
    {
        $becomesGet = in_array($redirectStatus, self::REDIRECTS_TO_GET, true) && $hop->method->sendsBody();
        $sameOrigin = self::sameOrigin($from, $to);

        return new ProbeHop(
            $becomesGet ? HttpMethod::Get : $hop->method,
            $hop->sendsBody && ! $becomesGet && $sameOrigin,
            $hop->sendsUserHeaders && $sameOrigin,
        );
    }

    public static function sameOrigin(TargetUrl $from, TargetUrl $to): bool
    {
        return $from->scheme === $to->scheme
            && strtolower($from->host) === strtolower($to->host)
            && $from->port === $to->port;
    }

    public static function headers(ProbeHop $hop, MonitorRequest $request, string $userAgent): array
    {
        $headers = [self::USER_AGENT => $userAgent];
        if (! $hop->sendsUserHeaders) {
            return $headers;
        }
        foreach ($request->headers as $header) {
            if (! $header instanceof RequestHeader) {
                continue;
            }
            foreach (array_keys($headers) as $name) {
                if ($header->isNamed($name)) {
                    unset($headers[$name]);
                }
            }
            $headers[$header->name] = $header->value;
        }

        return $headers;
    }
}
