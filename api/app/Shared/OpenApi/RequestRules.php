<?php

declare(strict_types=1);

namespace App\Shared\OpenApi;

use Dedoc\Scramble\Support\RouteInfo;

final readonly class RequestRules
{
    public function __construct(private array $rulesByAction) {}

    public function of(RouteInfo $route): ?array
    {
        $rules = $this->rulesByAction[$route->className().'@'.$route->methodName()] ?? null;

        return is_array($rules) ? $rules : null;
    }
}
