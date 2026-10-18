<?php

declare(strict_types=1);

namespace App\Monitoring\Http\Requests;

use App\Monitoring\Settings\RequestSettings;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class AllowedHeaderName implements ValidationRule
{
    public function __construct(private RequestSettings $settings) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && $this->settings->refusesHeader($value)) {
            $fail('monitoring.validation.refused_header')->translate();
        }
    }
}
