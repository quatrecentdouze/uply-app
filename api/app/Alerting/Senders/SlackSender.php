<?php

declare(strict_types=1);

namespace App\Alerting\Senders;

use App\Alerting\Rules\DeliveryRules;
use App\Alerting\Rules\MessageFormatRules;
use App\Alerting\Settings\AlertingSettings;
use App\Alerting\Values\AlertField;
use App\Alerting\Values\AlertMessage;
use App\Alerting\Values\SendReport;
use App\Alerting\Values\WebhookUrl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;

final readonly class SlackSender
{
    private const string TIMEOUT_ERROR = 'cURL error 28';

    private const string JSON = 'application/json';

    public function __construct(
        private Factory $http,
        private AlertingSettings $settings,
    ) {}

    public function send(WebhookUrl $url, AlertMessage $message): SendReport
    {
        $webhooks = $this->settings->webhooks;
        try {
            $response = $this->http
                ->withoutRedirecting()
                ->timeout($webhooks->timeoutSeconds)
                ->connectTimeout($webhooks->connectTimeoutSeconds)
                ->withBody(json_encode($this->payload($message), JSON_THROW_ON_ERROR), self::JSON)
                ->post($url->value);
        } catch (ConnectionException $exception) {
            return SendReport::transportFailed(str_contains($exception->getMessage(), self::TIMEOUT_ERROR) ? 'timeout' : 'connection error');
        }

        if ($response->successful()) {
            return SendReport::accepted($response->status());
        }

        return SendReport::responded(
            $response->status(),
            DeliveryRules::retryAfterSeconds(null, $response->header('Retry-After') ?: null),
            MessageFormatRules::withoutUrls($response->body()),
        );
    }

    public function payload(AlertMessage $message): array
    {
        $title = MessageFormatRules::slackEscape($message->title);
        $blocks = [[
            'type' => 'section',
            'text' => ['type' => 'mrkdwn', 'text' => "*{$title}*\n".MessageFormatRules::slackEscape($message->summary)],
        ]];
        if ($message->hasFields()) {
            $blocks[] = [
                'type' => 'section',
                'fields' => $message->mapFields(fn (AlertField $field): array => [
                    'type' => 'mrkdwn',
                    'text' => '*'.MessageFormatRules::slackEscape($field->label)."*\n".($field->time === null
                        ? MessageFormatRules::slackEscape($field->text)
                        : MessageFormatRules::slackDate($field->time, $field->text)),
                ]),
            ];
        }
        if ($message->link !== null) {
            $blocks[] = [
                'type' => 'section',
                'text' => ['type' => 'mrkdwn', 'text' => MessageFormatRules::slackLink($message->link, $message->linkLabel)],
            ];
        }

        return [
            'text' => $title,
            'attachments' => [['color' => $message->color, 'blocks' => $blocks]],
        ];
    }
}
