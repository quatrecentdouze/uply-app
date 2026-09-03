<?php

declare(strict_types=1);

use App\Monitoring\Enums\CheckOutcome;
use App\Monitoring\Enums\FailureKind;
use App\Monitoring\Rules\CheckOutcomeRules;
use App\Monitoring\Values\AcceptedStatusCodes;
use App\Monitoring\Values\ProbeResult;
use App\Monitoring\Values\ProbeTimings;

it('judges the http status against the accepted ranges', function (array $ranges, int $status, CheckOutcome $outcome, ?FailureKind $kind): void {
    $verdict = CheckOutcomeRules::evaluate(AcceptedStatusCodes::parse($ranges), ProbeResult::responded($status, new ProbeTimings(totalMs: 80)));

    expect($verdict->outcome)->toBe($outcome)
        ->and($verdict->failureKind)->toBe($kind);
})->with([
    'ok inside the default range' => [['200-299'], 200, CheckOutcome::Up, null],
    'last code of the range' => [['200-299'], 299, CheckOutcome::Up, null],
    'server error' => [['200-299'], 503, CheckOutcome::Down, FailureKind::HttpStatus],
    'redirect not followed' => [['200-299'], 301, CheckOutcome::Down, FailureKind::HttpStatus],
    'extra single code' => [['200-299', '304'], 304, CheckOutcome::Up, null],
    'teapot range' => [['418'], 418, CheckOutcome::Up, null],
]);

it('keeps the failure kind reported by the probe', function (FailureKind $kind, ?int $status): void {
    $verdict = CheckOutcomeRules::evaluate(
        AcceptedStatusCodes::parse(['200-299']),
        ProbeResult::failed($kind, 'probe failure', ProbeTimings::none(), $status),
    );

    expect($verdict->outcome)->toBe(CheckOutcome::Down)
        ->and($verdict->failureKind)->toBe($kind);
})->with([
    'timeout' => [FailureKind::Timeout, null],
    'dns' => [FailureKind::Dns, null],
    'blocked target' => [FailureKind::BlockedTarget, null],
    'body too large after a 200' => [FailureKind::BodyTooLarge, 200],
]);

it('refuses a probe result that claims an http status failure', function (): void {
    expect(fn () => ProbeResult::failed(FailureKind::HttpStatus, 'judged by the rules', ProbeTimings::none()))
        ->toThrow(InvalidArgumentException::class);
});

it('cuts long error messages to the column size', function (): void {
    $result = ProbeResult::failed(FailureKind::Unknown, str_repeat('é', 800), ProbeTimings::none());

    expect(mb_strlen((string) $result->errorMessage))->toBe(ProbeResult::MAX_ERROR_MESSAGE_LENGTH);
});
