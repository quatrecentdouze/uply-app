<?php

declare(strict_types=1);

use App\Monitoring\Enums\CheckOutcome;
use App\Monitoring\Enums\FailureKind;
use App\Monitoring\Enums\KeywordMatch;
use App\Monitoring\Rules\CheckOutcomeRules;
use App\Monitoring\Values\AcceptedStatusCodes;
use App\Monitoring\Values\CheckVerdict;
use App\Monitoring\Values\Keyword;
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

function keywordVerdict(?string $body, string $keyword, KeywordMatch $match, int $status = 200): CheckVerdict
{
    return CheckOutcomeRules::evaluate(AcceptedStatusCodes::parse(['200-299']), ProbeResult::responded($status, new ProbeTimings(totalMs: 80), $body), new Keyword($keyword, $match));
}

it('looks for a keyword that must be present', function (?string $body, ?FailureKind $kind): void {
    $verdict = keywordVerdict($body, 'All systems go', KeywordMatch::Present);

    expect($verdict->failureKind)->toBe($kind)
        ->and($verdict->diagnostic)->toBe($kind === null ? null : CheckOutcomeRules::KEYWORD_MISSING);
})->with([
    'present' => ['<p>All systems go</p>', null],
    'missing' => ['<p>Degraded</p>', FailureKind::KeywordMissing],
    'other case' => ['<p>all systems go</p>', FailureKind::KeywordMissing],
    'no body read' => [null, FailureKind::KeywordMissing],
]);

it('looks for a keyword that must be absent', function (?string $body, ?FailureKind $kind): void {
    $verdict = keywordVerdict($body, 'Maintenance', KeywordMatch::Absent);

    expect($verdict->failureKind)->toBe($kind)
        ->and($verdict->diagnostic)->toBe($kind === null ? null : CheckOutcomeRules::KEYWORD_FOUND);
})->with([
    'absent' => ['<p>Open</p>', null],
    'found' => ['<p>Maintenance window</p>', FailureKind::KeywordFound],
    'other case' => ['<p>maintenance window</p>', null],
    'no body read' => [null, null],
]);

it('compares bytes without converting the charset', function (): void {
    expect(keywordVerdict("caf\xE9", 'café', KeywordMatch::Present)->failureKind)->toBe(FailureKind::KeywordMissing)
        ->and(keywordVerdict('café', 'café', KeywordMatch::Present)->failureKind)->toBeNull();
});

it('finds a keyword at the very end of a body as long as the cap', function (): void {
    $body = str_repeat('u', 1024 * 1024 - 4).'TAIL';

    expect(keywordVerdict($body, 'TAIL', KeywordMatch::Present)->failureKind)->toBeNull();
});

it('judges the status before the keyword', function (): void {
    $verdict = keywordVerdict('<p>All systems go</p>', 'All systems go', KeywordMatch::Present, 503);

    expect($verdict->failureKind)->toBe(FailureKind::HttpStatus)
        ->and($verdict->diagnostic)->toBeNull();
});

it('keeps a failure of the probe before the keyword', function (): void {
    $verdict = CheckOutcomeRules::evaluate(
        AcceptedStatusCodes::parse(['200-299']),
        ProbeResult::failed(FailureKind::BodyTooLarge, 'body larger than the cap', ProbeTimings::none(), 200),
        new Keyword('All systems go', KeywordMatch::Present),
    );

    expect($verdict->failureKind)->toBe(FailureKind::BodyTooLarge);
});

it('refuses an empty keyword', function (): void {
    expect(fn () => new Keyword('', KeywordMatch::Present))->toThrow(InvalidArgumentException::class);
});
