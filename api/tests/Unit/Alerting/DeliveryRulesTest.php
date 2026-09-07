<?php

declare(strict_types=1);

use App\Alerting\Enums\DecisionOutcome;
use App\Alerting\Rules\DeliveryRules;
use App\Alerting\Values\DeliveryDecision;
use App\Alerting\Values\RetryPolicy;
use App\Alerting\Values\SendReport;

function shippedRetryPolicy(): RetryPolicy
{
    return new RetryPolicy(5, [10, 30, 60, 120]);
}

it('marks an accepted send as sent', function (SendReport $report): void {
    expect(DeliveryRules::decide($report, 1, shippedRetryPolicy())->outcome)->toBe(DecisionOutcome::Sent);
})->with([
    'mail accepted' => [SendReport::accepted()],
    'webhook 204' => [SendReport::responded(204, null, '')],
    'webhook 200' => [SendReport::responded(200, null, '')],
]);

it('fails at once and disables the channel when the webhook is rejected', function (int $status): void {
    $decision = DeliveryRules::decide(SendReport::responded($status, null, 'Unknown Webhook'), 1, shippedRetryPolicy());

    expect($decision->outcome)->toBe(DecisionOutcome::Failed)
        ->and($decision->disablesChannel)->toBeTrue()
        ->and($decision->lastError)->toBe("HTTP {$status} Unknown Webhook");
})->with([401, 403, 404, 410]);

it('fails at once when the channel settings cannot be used', function (): void {
    $decision = DeliveryRules::decide(SendReport::refused('invalid channel settings'), 1, shippedRetryPolicy());

    expect($decision->outcome)->toBe(DecisionOutcome::Failed)
        ->and($decision->disablesChannel)->toBeFalse()
        ->and($decision->lastError)->toBe('invalid channel settings');
});

it('fails at once without disabling on any other client error or a redirect', function (int $status): void {
    $decision = DeliveryRules::decide(SendReport::responded($status, null, 'invalid_payload'), 1, shippedRetryPolicy());

    expect($decision->outcome)->toBe(DecisionOutcome::Failed)
        ->and($decision->disablesChannel)->toBeFalse();
})->with([400, 413, 422, 301]);

it('retries server and transport errors with the backoff of each attempt', function (int $attemptsMade, int $delay): void {
    $server = DeliveryRules::decide(SendReport::responded(503, null, ''), $attemptsMade, shippedRetryPolicy());
    $transport = DeliveryRules::decide(SendReport::transportFailed('timeout'), $attemptsMade, shippedRetryPolicy());

    expect($server->outcome)->toBe(DecisionOutcome::Retry)
        ->and($server->retryInSeconds)->toBe($delay)
        ->and($transport->outcome)->toBe(DecisionOutcome::Retry)
        ->and($transport->retryInSeconds)->toBe($delay)
        ->and($transport->lastError)->toBe('timeout');
})->with([[1, 10], [2, 30], [3, 60], [4, 120]]);

it('fails after the last try', function (SendReport $report): void {
    $decision = DeliveryRules::decide($report, 5, shippedRetryPolicy());

    expect($decision->outcome)->toBe(DecisionOutcome::Failed)
        ->and($decision->disablesChannel)->toBeFalse();
})->with([
    'server error' => [SendReport::responded(500, null, '')],
    'transport error' => [SendReport::transportFailed('connection error')],
    'rate limited' => [SendReport::responded(429, 3, '')],
]);

it('waits the delay asked by a 429, or the backoff without one', function (): void {
    expect(DeliveryRules::decide(SendReport::responded(429, 7, 'You are being rate limited.'), 1, shippedRetryPolicy())->retryInSeconds)->toBe(7)
        ->and(DeliveryRules::decide(SendReport::responded(429, null, ''), 2, shippedRetryPolicy())->retryInSeconds)->toBe(30);
});

it('reads the 429 delay from the discord body or the retry after header', function (mixed $body, ?string $header, ?int $seconds): void {
    expect(DeliveryRules::retryAfterSeconds($body, $header))->toBe($seconds);
})->with([
    'discord float' => [1.234, null, 2],
    'discord integer' => [3, null, 3],
    'header seconds' => [null, '12', 12],
    'body wins' => [0.2, '60', 1],
    'http date ignored' => [null, 'Wed, 21 Oct 2026 07:28:00 GMT', null],
    'nothing' => [null, null, null],
]);

it('cuts a long diagnostic to the column size', function (): void {
    $decision = DeliveryRules::decide(SendReport::responded(400, null, str_repeat('x', 900)), 1, shippedRetryPolicy());

    expect(mb_strlen((string) $decision->lastError))->toBe(DeliveryDecision::MAX_LAST_ERROR_LENGTH);
});
