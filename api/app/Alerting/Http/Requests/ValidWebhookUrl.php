<?php

declare(strict_types=1);

namespace App\Alerting\Http\Requests;

use App\Alerting\Enums\ChannelType;
use App\Alerting\Settings\WebhookSettings;
use App\Alerting\Values\WebhookUrl;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

final readonly class ValidWebhookUrl implements ValidationRule
{
    private const string MESSAGE_PREFIX = 'alerting.validation.webhook_url.';

    public function __construct(
        private WebhookSettings $webhooks,
        private ?ChannelType $type,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->type === null || ! $this->type->usesWebhook()) {
            return;
        }
        try {
            WebhookUrl::parse(is_string($value) ? $value : '', $this->webhooks->policyFor($this->type));
        } catch (InvalidArgumentException) {
            $fail(self::MESSAGE_PREFIX.$this->type->value)->translate();
        }
    }
}
