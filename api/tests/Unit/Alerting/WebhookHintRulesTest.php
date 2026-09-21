<?php

declare(strict_types=1);

use App\Alerting\Rules\WebhookHintRules;

it('keeps only the host and the last four characters of a webhook', function (string $url, string $host, string $lastFour): void {
    $hint = WebhookHintRules::hint($url);

    expect($hint?->host)->toBe($host)
        ->and($hint?->lastFour)->toBe($lastFour);
})->with([
    'discord' => ['https://discord.com/api/webhooks/1234567890/abcdefghijklmnopWXYZ', 'discord.com', 'WXYZ'],
    'slack' => ['https://hooks.slack.com/services/T000/B000/secret9z8y', 'hooks.slack.com', '9z8y'],
    'upper case host' => ['https://Discord.COM/api/webhooks/1/0123456789abcdef', 'discord.com', 'cdef'],
]);

it('never hints at a url too short to hide its secret', function (string $url): void {
    expect(WebhookHintRules::hint($url))->toBeNull();
})->with([
    'short path' => ['https://hooks.slack.com/s/abcd'],
    'no host' => ['not a url'],
    'empty' => [''],
]);

it('never shows more than four characters of the path', function (): void {
    $url = 'https://discord.com/api/webhooks/1234567890/discord-test-token';
    $hint = WebhookHintRules::hint($url);

    expect(str_contains($url, (string) $hint?->lastFour))->toBeTrue()
        ->and(mb_strlen((string) $hint?->lastFour))->toBe(4)
        ->and((string) $hint?->host)->not->toContain('token');
});
