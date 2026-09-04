<?php

declare(strict_types=1);

namespace App\Monitoring\Probe;

use App\Monitoring\Rules\TargetRules;
use App\Monitoring\Settings\ProbeSettings;
use App\Monitoring\Values\PinnedTarget;

final readonly class DnsTargetGuard implements TargetGuard
{
    public function __construct(
        private HostResolver $resolver,
        private ProbeSettings $settings,
    ) {}

    public function pin(string $url): PinnedTarget
    {
        $target = TargetRules::parse($url);
        if ($target->hostIsAddress) {
            return TargetRules::pin($target, [$target->host], $this->settings->allowPrivateTargets, null);
        }
        $resolution = $this->resolver->resolve($target->host);

        return TargetRules::pin($target, $resolution->addresses, $this->settings->allowPrivateTargets, $resolution->elapsedMs);
    }
}
