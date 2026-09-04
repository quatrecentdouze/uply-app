<?php

declare(strict_types=1);

namespace App\Monitoring\Probe;

use App\Monitoring\Values\HostResolution;
use Throwable;

final class SystemHostResolver implements HostResolver
{
    private const int NANOSECONDS_PER_MILLISECOND = 1_000_000;

    public function resolve(string $host): HostResolution
    {
        $startedAt = hrtime(true);
        $addresses = $this->dnsRecords($host);
        if ($addresses === []) {
            $addresses = $this->systemResolver($host);
        }

        return new HostResolution($addresses, intdiv(hrtime(true) - $startedAt, self::NANOSECONDS_PER_MILLISECOND));
    }

    private function dnsRecords(string $host): array
    {
        try {
            $records = dns_get_record($host, DNS_A | DNS_AAAA);
        } catch (Throwable) {
            return [];
        }
        $addresses = [];
        foreach (is_array($records) ? $records : [] as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($address) && ! in_array($address, $addresses, true)) {
                $addresses[] = $address;
            }
        }

        return $addresses;
    }

    private function systemResolver(string $host): array
    {
        try {
            $found = gethostbynamel($host);
        } catch (Throwable) {
            return [];
        }
        $addresses = [];
        foreach (is_array($found) ? $found : [] as $address) {
            if (! in_array($address, $addresses, true)) {
                $addresses[] = $address;
            }
        }

        return $addresses;
    }
}
