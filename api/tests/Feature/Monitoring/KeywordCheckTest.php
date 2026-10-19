<?php

declare(strict_types=1);

use App\Alerting\Mail\AlertMail;
use App\Alerting\Models\AlertChannel;
use App\Monitoring\Enums\FailureKind;
use App\Monitoring\Enums\KeywordMatch;
use App\Monitoring\Events\MonitorWentDownEvent;
use App\Monitoring\Models\Monitor;
use App\Monitoring\Probe\HostResolver;
use App\Monitoring\Rules\CheckOutcomeRules;
use App\Monitoring\Storage\CheckStorageMaintenance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FrozenClock;
use Tests\Support\StaticHostResolver;

beforeEach(function (): void {
    $this->clock = freezeClock('2027-03-10 12:00:00');
    app(CheckStorageMaintenance::class)->run($this->clock->now());
    app()->instance(HostResolver::class, new StaticHostResolver);
    Mail::fake();
});

function keywordMonitor(FrozenClock $clock, string $keyword, KeywordMatch $match): Monitor
{
    $monitor = Monitor::factory()->create([
        'url' => 'https://status.example.com/',
        'keyword' => $keyword,
        'keyword_match' => $match,
        'next_check_at' => $clock->now(),
    ]);
    $channel = AlertChannel::factory()->create(['organization_id' => $monitor->organization_id, 'settings' => [AlertChannel::RECIPIENTS_SETTING => ['ops@example.com']]]);
    DB::table('alert_channel_monitor')->insert(['alert_channel_id' => $channel->id, 'monitor_id' => $monitor->id]);

    return $monitor;
}

function runKeywordChecks(FrozenClock $clock, array $times): void
{
    foreach ($times as $time) {
        $clock->moveTo($clock->now()->setTimeFromTimeString($time));
        test()->artisan('checks:dispatch')->assertSuccessful();
    }
}

it('opens an incident when the keyword leaves the page and resolves it when the keyword is back', function (): void {
    Http::fake(['status.example.com/*' => Http::sequence()
        ->push('<p>All systems go</p>', 200)->push('<p>All systems go</p>', 200)
        ->push('<p>Degraded</p>', 200)->push('<p>Degraded</p>', 200)
        ->push('<p>All systems go</p>', 200)->push('<p>All systems go</p>', 200)]);
    $monitor = keywordMonitor($this->clock, 'All systems go', KeywordMatch::Present);

    runKeywordChecks($this->clock, ['12:00:00', '12:00:20', '12:01:20', '12:01:40']);

    $incident = DB::table('incidents')->where('monitor_id', $monitor->id)->sole();
    expect($monitor->fresh()?->state->value)->toBe('down')
        ->and($incident->cause_failure_kind)->toBe(FailureKind::KeywordMissing->value)
        ->and($incident->cause_message)->toBe(CheckOutcomeRules::KEYWORD_MISSING)
        ->and(DB::table('check_results')->where('outcome', 'down')->pluck('error_message')->unique()->all())->toBe([CheckOutcomeRules::KEYWORD_MISSING])
        ->and(DB::table('check_results')->where('outcome', 'down')->pluck('http_status')->unique()->all())->toBe([200]);
    Mail::assertSent(AlertMail::class, fn (AlertMail $mail): bool => str_contains($mail->render(), 'Keyword not found'));

    runKeywordChecks($this->clock, ['12:02:40', '12:03:00']);

    expect($monitor->fresh()?->state->value)->toBe('up')
        ->and(DB::table('incidents')->where('monitor_id', $monitor->id)->value('resolved_at'))->not->toBeNull();
});

it('goes down with keyword_found when an unwanted keyword shows up', function (): void {
    Event::fake([MonitorWentDownEvent::class]);
    Http::fake(['status.example.com/*' => Http::sequence()->push('<p>Maintenance tonight</p>', 200)->push('<p>Maintenance tonight</p>', 200)]);
    $monitor = keywordMonitor($this->clock, 'Maintenance', KeywordMatch::Absent);

    runKeywordChecks($this->clock, ['12:00:00', '12:00:20']);

    expect($monitor->fresh()?->state->value)->toBe('down');
    Event::assertDispatched(MonitorWentDownEvent::class, fn (MonitorWentDownEvent $event): bool => $event->failureKind === FailureKind::KeywordFound
        && $event->errorMessage === CheckOutcomeRules::KEYWORD_FOUND);
});

it('keeps the body out of every stored result', function (): void {
    Http::fake(['status.example.com/*' => Http::response('<p>body-only-text Degraded</p>', 200)]);
    keywordMonitor($this->clock, 'All systems go', KeywordMatch::Present);

    runKeywordChecks($this->clock, ['12:00:00', '12:00:20']);

    expect(DB::table('check_results')->get()->toJson())->not->toContain('body-only-text')
        ->and(DB::table('incidents')->get()->toJson())->not->toContain('body-only-text');
});
