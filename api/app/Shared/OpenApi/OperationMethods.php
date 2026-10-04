<?php

declare(strict_types=1);

namespace App\Shared\OpenApi;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;

final readonly class OperationMethods
{
    public function __construct(private array $methodByAction) {}

    public function of(Route $route): string
    {
        $method = $this->methodByAction[ltrim($route->getActionName(), '\\')] ?? $route->methods()[0] ?? null;

        return strtolower(is_string($method) ? $method : Request::METHOD_GET);
    }
}
