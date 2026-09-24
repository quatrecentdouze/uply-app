<?php

declare(strict_types=1);

namespace App\StatusPages\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class MonitorOfOrganization implements ValidationRule
{
    public function __construct(private array $allowedMonitorIds) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! in_array(strtolower($value), $this->allowedMonitorIds, true)) {
            $fail('status_pages.validation.monitor')->translate();
        }
    }
}
