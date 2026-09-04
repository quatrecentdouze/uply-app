<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Monitoring\Probe\HostResolver;
use App\Monitoring\Values\HostResolution;

final class StaticHostResolver implements HostResolver
{
    public array $resolvedHosts = [];

    public function __construct(
        private readonly array $addressesByHost = [],
        private readonly array $defaultAddresses = ['93.184.215.14'],
    ) {}

    public function resolve(string $host): HostResolution
    {
        $this->resolvedHosts[] = $host;
        $addresses = $this->addressesByHost[$host] ?? $this->defaultAddresses;

        return new HostResolution(is_array($addresses) ? $addresses : [], 1);
    }
}
