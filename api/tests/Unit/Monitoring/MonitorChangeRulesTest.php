<?php

declare(strict_types=1);

use App\Monitoring\Enums\HttpMethod;
use App\Monitoring\Enums\KeywordMatch;
use App\Monitoring\Rules\MonitorChangeRules;
use App\Monitoring\Values\AcceptedStatusCodes;
use App\Monitoring\Values\CheckDefinition;
use App\Monitoring\Values\MonitorRequest;
use App\Monitoring\Values\RequestHeader;
use Carbon\CarbonImmutable;

function definition(array $changes = []): CheckDefinition
{
    $values = [...[
        'url' => 'https://api.example.com/health',
        'method' => HttpMethod::Get,
        'codes' => ['200-299'],
        'followRedirects' => true,
        'interval' => 300,
        'paused' => false,
        'request' => new MonitorRequest([new RequestHeader('Authorization', 'Bearer a')], null),
        'keyword' => 'All systems go',
        'keywordMatch' => KeywordMatch::Present,
    ], ...$changes];

    return new CheckDefinition(
        $values['url'],
        $values['method'],
        AcceptedStatusCodes::parse($values['codes']),
        $values['followRedirects'],
        $values['interval'],
        $values['paused'],
        $values['request'],
        $values['keyword'],
        $values['keywordMatch'],
    );
}

beforeEach(function (): void {
    $this->now = CarbonImmutable::parse('2027-03-10 12:00:00', 'UTC');
    $this->nextCheckAt = $this->now->addSeconds(240);
});

it('checks at once and clears the streak when what is judged changes', function (array $changes): void {
    $plan = MonitorChangeRules::plan(definition(), definition($changes), $this->nextCheckAt, $this->now);

    expect($plan->clearsStreak)->toBeTrue()
        ->and($plan->nextCheckAt->equalTo($this->now))->toBeTrue();
})->with([
    'url' => [['url' => 'https://api.example.com/ready']],
    'method' => [['method' => HttpMethod::Head]],
    'accepted codes' => [['codes' => ['200-299', '304']]],
    'redirects' => [['followRedirects' => false]],
    'post method' => [['method' => HttpMethod::Post]],
    'header value' => [['request' => new MonitorRequest([new RequestHeader('Authorization', 'Bearer b')], null)]],
    'header removed' => [['request' => MonitorRequest::none()]],
    'body' => [['request' => new MonitorRequest([new RequestHeader('Authorization', 'Bearer a')], '{}')]],
    'keyword' => [['keyword' => 'Operational']],
    'keyword removed' => [['keyword' => null]],
    'keyword match' => [['keywordMatch' => KeywordMatch::Absent]],
]);

it('keeps the streak and the schedule for a rename or a timeout change', function (): void {
    $plan = MonitorChangeRules::plan(definition(), definition(), $this->nextCheckAt, $this->now);

    expect($plan->clearsStreak)->toBeFalse()
        ->and($plan->nextCheckAt->equalTo($this->nextCheckAt))->toBeTrue();
});

it('keeps the streak while pausing', function (): void {
    $plan = MonitorChangeRules::plan(definition(), definition(['paused' => true]), $this->nextCheckAt, $this->now);

    expect($plan->clearsStreak)->toBeFalse()
        ->and($plan->nextCheckAt->equalTo($this->nextCheckAt))->toBeTrue();
});

it('checks at once on resume', function (): void {
    $plan = MonitorChangeRules::plan(definition(['paused' => true]), definition(), $this->nextCheckAt->subDay(), $this->now);

    expect($plan->clearsStreak)->toBeFalse()
        ->and($plan->nextCheckAt->equalTo($this->now))->toBeTrue();
});

it('brings the next check closer when the interval gets shorter', function (): void {
    $plan = MonitorChangeRules::plan(definition(), definition(['interval' => 60]), $this->nextCheckAt, $this->now);

    expect($plan->clearsStreak)->toBeFalse()
        ->and($plan->nextCheckAt->equalTo($this->now->addSeconds(60)))->toBeTrue();
});

it('keeps a next check that is already closer than the new interval', function (): void {
    $plan = MonitorChangeRules::plan(definition(), definition(['interval' => 3600]), $this->nextCheckAt, $this->now);

    expect($plan->nextCheckAt->equalTo($this->nextCheckAt))->toBeTrue();
});
