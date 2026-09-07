<?php

declare(strict_types=1);

namespace App\Alerting\Senders;

use App\Alerting\Enums\ChannelType;
use App\Alerting\Models\AlertChannel;
use App\Alerting\Settings\AlertingSettings;
use App\Alerting\Values\AlertMessage;
use App\Alerting\Values\EmailRecipients;
use App\Alerting\Values\SendReport;
use App\Alerting\Values\WebhookUrl;
use InvalidArgumentException;

final readonly class ChannelSender
{
    public function __construct(
        private DiscordSender $discord,
        private SlackSender $slack,
        private MailSender $mail,
        private AlertingSettings $settings,
    ) {}

    public function send(AlertChannel $channel, AlertMessage $message): SendReport
    {
        try {
            $destination = $this->destination($channel);
        } catch (InvalidArgumentException) {
            return SendReport::refused('invalid channel settings');
        }

        return match (true) {
            $destination instanceof EmailRecipients => $this->mail->send($destination, $message),
            $channel->type === ChannelType::Discord => $this->discord->send($destination, $message),
            default => $this->slack->send($destination, $message),
        };
    }

    private function destination(AlertChannel $channel): EmailRecipients|WebhookUrl
    {
        if ($channel->type === ChannelType::Email) {
            return $channel->recipients($this->settings->email->minRecipients, $this->settings->email->maxRecipients);
        }

        return $channel->webhookUrl($this->settings->webhooks->policyFor($channel->type));
    }
}
