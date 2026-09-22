<?php

declare(strict_types=1);

namespace App\Alerting\Http\Requests;

use App\Alerting\Settings\EmailSettings;
use App\Alerting\Values\EmailRecipients;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

final readonly class ValidRecipients implements ValidationRule
{
    private const string MESSAGE_KEY = 'alerting.validation.recipients';

    public function __construct(private EmailSettings $email) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            EmailRecipients::parse(is_array($value) ? $value : [], $this->email->minRecipients, $this->email->maxRecipients);
        } catch (InvalidArgumentException) {
            $fail(self::MESSAGE_KEY)->translate(['min' => (string) $this->email->minRecipients, 'max' => (string) $this->email->maxRecipients]);
        }
    }
}
