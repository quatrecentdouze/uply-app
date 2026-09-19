<?php

declare(strict_types=1);

namespace App\Monitoring\Http\Requests;

use App\Monitoring\Values\StatusCodeRange;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

final readonly class ValidStatusCodeRange implements ValidationRule
{
    private const string MESSAGE_KEY = 'monitoring.validation.status_code_range';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            StatusCodeRange::parse(is_string($value) ? $value : '');
        } catch (InvalidArgumentException) {
            $fail(self::MESSAGE_KEY)->translate();
        }
    }
}
