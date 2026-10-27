<?php

declare(strict_types=1);

namespace App\Alerting\Http\Requests;

use App\Alerting\Enums\ChannelType;
use App\Alerting\Settings\AlertingSettings;
use App\Alerting\Values\ChannelDraft;
use App\Alerting\Values\EmailRecipients;
use App\Alerting\Values\WebhookUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final readonly class AlertChannelFields
{
    public const int NAME_MIN_LENGTH = 1;

    private const int EMAIL_MAX_LENGTH = 255;

    public function __construct(private AlertingSettings $settings) {}

    public function rules(?ChannelType $type, bool $partial): array
    {
        $presence = $partial ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'string', 'min:'.self::NAME_MIN_LENGTH, 'max:'.$this->settings->channels->nameMaxLength],
            'is_enabled' => ['sometimes', 'boolean'],
            'reminder_interval_minutes' => ['sometimes', 'nullable', 'integer', Rule::in($this->settings->reminders->intervalsMinutes)],
            'recipients' => $type === ChannelType::Email
                ? [$presence, 'array', 'list', 'max:'.$this->settings->email->maxRecipients, new ValidRecipients($this->settings->email)]
                : ['prohibited'],
            'recipients.*' => ['string', 'max:'.self::EMAIL_MAX_LENGTH],
            'webhook_url' => $type?->usesWebhook() === true
                ? [$presence, 'string', 'max:'.$this->settings->channels->webhookUrlMaxLength, new ValidWebhookUrl($this->settings->webhooks, $type)]
                : ['prohibited'],
        ];
    }

    public function draft(FormRequest $request, ChannelType $type): ChannelDraft
    {
        $email = $this->settings->email;

        return new ChannelDraft(
            name: $request->has('name') ? $request->string('name')->toString() : null,
            isEnabled: $request->has('is_enabled') ? $request->boolean('is_enabled') : null,
            recipients: $type === ChannelType::Email && $request->has('recipients')
                ? EmailRecipients::parse($request->array('recipients'), $email->minRecipients, $email->maxRecipients)
                : null,
            webhook: $type->usesWebhook() && $request->has('webhook_url')
                ? WebhookUrl::parse($request->string('webhook_url')->toString(), $this->settings->webhooks->policyFor($type))
                : null,
            changesReminder: $request->has('reminder_interval_minutes'),
            reminderIntervalMinutes: $request->filled('reminder_interval_minutes') ? $request->integer('reminder_interval_minutes') : null,
        );
    }
}
