<?php

declare(strict_types=1);

namespace App\Monitoring\Http\Requests;

use App\Monitoring\Rules\MonitorUrlRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class ValidMonitorUrl implements ValidationRule
{
    public function __construct(private bool $allowPrivateTargets) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $problem = is_string($value) ? MonitorUrlRules::problem($value, $this->allowPrivateTargets) : null;
        if ($problem !== null) {
            $fail($problem->messageKey())->translate();
        }
    }
}
