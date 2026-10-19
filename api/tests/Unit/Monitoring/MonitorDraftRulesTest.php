<?php

declare(strict_types=1);

use App\Monitoring\Enums\DraftConflict;
use App\Monitoring\Enums\HttpMethod;
use App\Monitoring\Enums\KeywordMatch;
use App\Monitoring\Rules\MonitorDraftRules;
use App\Monitoring\Values\AcceptedStatusCodes;
use App\Monitoring\Values\ConfirmationThresholds;
use App\Monitoring\Values\MonitorDraft;
use App\Monitoring\Values\MonitorRequest;

function draftWith(HttpMethod $method, ?string $body): MonitorDraft
{
    return new MonitorDraft(
        'API',
        'https://api.example.com/health',
        $method,
        60,
        10,
        AcceptedStatusCodes::parse(['200-299']),
        true,
        null,
        KeywordMatch::Present,
        new ConfirmationThresholds(2, 2),
        new MonitorRequest([], $body),
    );
}

it('finds a body sent with a method that sends none', function (HttpMethod $method): void {
    expect(MonitorDraftRules::conflicts(draftWith($method, '{}')))->toBe([DraftConflict::BodyWithoutBodyMethod]);
})->with([HttpMethod::Get, HttpMethod::Head]);

it('accepts a body with post, put and patch, and no body with any method', function (HttpMethod $method): void {
    expect(MonitorDraftRules::conflicts(draftWith($method, $method->sendsBody() ? '{}' : null)))->toBe([]);
})->with(HttpMethod::cases());

it('names the field and the text of a conflict', function (): void {
    expect(DraftConflict::BodyWithoutBodyMethod->field())->toBe('request_body')
        ->and(DraftConflict::BodyWithoutBodyMethod->messageKey())->toBe('monitoring.validation.conflict.request_body');
});
