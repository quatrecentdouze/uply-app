<?php

declare(strict_types=1);

namespace App\Alerting\Models;

use App\Alerting\Enums\ChannelType;
use App\Alerting\Enums\DisabledReason;
use App\Alerting\Rules\WebhookHintRules;
use App\Alerting\Values\ChannelDraft;
use App\Alerting\Values\EmailRecipients;
use App\Alerting\Values\WebhookHint;
use App\Alerting\Values\WebhookPolicy;
use App\Alerting\Values\WebhookUrl;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'type', 'name', 'settings', 'is_enabled', 'disabled_reason'])]
#[Hidden(['settings'])]
final class AlertChannel extends Model
{
    use HasFactory;
    use HasUlids;

    public const string RECIPIENTS_SETTING = 'recipients';

    public const string WEBHOOK_URL_SETTING = 'webhook_url';

    public function webhookUrl(WebhookPolicy $policy): WebhookUrl
    {
        $url = $this->setting(self::WEBHOOK_URL_SETTING);

        return WebhookUrl::parse(is_string($url) ? $url : '', $policy);
    }

    public function recipients(int $minimum, int $maximum): EmailRecipients
    {
        $addresses = $this->setting(self::RECIPIENTS_SETTING);

        return EmailRecipients::parse(is_array($addresses) ? $addresses : [], $minimum, $maximum);
    }

    public function webhookHint(): ?WebhookHint
    {
        $url = $this->setting(self::WEBHOOK_URL_SETTING);

        return $this->type->usesWebhook() && is_string($url) ? WebhookHintRules::hint($url) : null;
    }

    public function storedRecipients(): array
    {
        $addresses = $this->setting(self::RECIPIENTS_SETTING);

        return is_array($addresses) ? array_values(array_filter($addresses, 'is_string')) : [];
    }

    public function applyDraft(ChannelDraft $draft): void
    {
        if ($draft->name !== null) {
            $this->name = $draft->name;
        }
        if ($draft->isEnabled !== null) {
            $this->is_enabled = $draft->isEnabled;
            $this->disabled_reason = $draft->isEnabled ? null : $this->disabled_reason;
        }
        if ($draft->recipients !== null) {
            $this->setAttribute('settings', [self::RECIPIENTS_SETTING => $draft->recipients->addresses]);
        }
        if ($draft->webhook !== null) {
            $this->setAttribute('settings', [self::WEBHOOK_URL_SETTING => $draft->webhook->value]);
        }
    }

    public function disable(DisabledReason $reason): void
    {
        $this->is_enabled = false;
        $this->disabled_reason = $reason;
    }

    protected function casts(): array
    {
        return [
            'type' => ChannelType::class,
            'settings' => 'encrypted:array',
            'is_enabled' => 'boolean',
            'disabled_reason' => DisabledReason::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    private function setting(string $key): mixed
    {
        $settings = $this->getAttribute('settings');

        return is_array($settings) ? ($settings[$key] ?? null) : null;
    }
}
