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
use Illuminate\Http\Client\Response;

final readonly class DiscordSender
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
                ->acceptJson()
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
            DeliveryRules::retryAfterSeconds($response->json('retry_after'), $response->header('Retry-After') ?: null),
            $this->providerError($response),
        );
    }

    public function payload(AlertMessage $message): array
    {
        $presentation = $this->settings->presentation;
        $embed = [
            'title' => MessageFormatRules::cut($message->title, $presentation->discordTitleMaxLength),
            'description' => MessageFormatRules::cut($message->summary, $presentation->discordDescriptionMaxLength),
            'color' => (int) hexdec(ltrim($message->color, '#')),
            'timestamp' => $message->occurredAt->utc()->toIso8601ZuluString(),
            'fields' => $message->mapFields(fn (AlertField $field): array => [
                'name' => MessageFormatRules::cut($field->label, $presentation->discordTitleMaxLength),
                'value' => MessageFormatRules::cut(
                    $field->time === null ? $field->text : MessageFormatRules::discordTimestamp($field->time),
                    $presentation->discordFieldValueMaxLength,
                ),
                'inline' => true,
            ]),
        ];
        if ($message->link !== null) {
            $embed['url'] = $message->link;
        }

        return [
            'username' => $presentation->discordUsername,
            'allowed_mentions' => ['parse' => []],
            'embeds' => [$embed],
        ];
    }

    private function providerError(Response $response): string
    {
        $message = $response->json('message');

        return MessageFormatRules::withoutUrls(is_string($message) ? $message : $response->body());
    }
}
