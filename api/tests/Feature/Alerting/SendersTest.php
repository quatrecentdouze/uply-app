<?php

declare(strict_types=1);

use App\Alerting\Enums\DecisionOutcome;
use App\Alerting\Mail\AlertMail;
use App\Alerting\Messages\AlertMessageBuilder;
use App\Alerting\Rules\DeliveryRules;
use App\Alerting\Senders\DiscordSender;
use App\Alerting\Senders\MailSender;
use App\Alerting\Senders\SlackSender;
use App\Alerting\Settings\AlertingSettings;
use App\Alerting\Values\AlertMessage;
use App\Alerting\Values\EmailRecipients;
use App\Alerting\Values\WebhookUrl;
use App\Monitoring\Contracts\MonitorView;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

const DISCORD_TEST_URL = 'https://discord.com/api/webhooks/1234/discord-token';
const SLACK_TEST_URL = 'https://hooks.slack.com/services/T1/B2/slack-token';

function hostileMonitor(): MonitorView
{
    return new MonitorView('01monitor', '01organization', '@everyone <b>Billing</b> & co', 'https://billing.example.com/health?a=1&b=2');
}

function downMessage(): AlertMessage
{
    return app(AlertMessageBuilder::class)->down(hostileMonitor(), '01incident', CarbonImmutable::parse('2026-10-28 20:54:51', 'UTC'), 'http_status', 500);
}

function discordUrl(): WebhookUrl
{
    return WebhookUrl::parse(DISCORD_TEST_URL, app(AlertingSettings::class)->webhooks->discord);
}

function slackUrl(): WebhookUrl
{
    return WebhookUrl::parse(SLACK_TEST_URL, app(AlertingSettings::class)->webhooks->slack);
}

it('builds the down, up and test texts from the language file', function (): void {
    $builder = app(AlertMessageBuilder::class);
    $up = $builder->up(hostileMonitor(), '01incident', CarbonImmutable::parse('2026-10-28 20:59:03', 'UTC'), 252);
    $test = $builder->test('Ops mail', CarbonImmutable::parse('2026-10-28 21:00:00', 'UTC'));

    expect(downMessage()->title)->toBe('@everyone <b>Billing</b> & co is down')
        ->and(downMessage()->fields[0]->text)->toBe('HTTP 500')
        ->and(downMessage()->fields[1]->text)->toBe('2026-10-28 20:54:51 UTC')
        ->and($up->title)->toBe('@everyone <b>Billing</b> & co is back up')
        ->and($up->summary)->toBe('Down for 4 min 12 s')
        ->and($test->title)->toBe('Test alert from Uply')
        ->and($test->link)->toBeNull()
        ->and($test->color)->toBe('#f36e29')
        ->and($builder->down(hostileMonitor(), '01', CarbonImmutable::now(), 'timeout', null)->fields[0]->text)->toBe('Timeout')
        ->and($builder->down(hostileMonitor(), '01', CarbonImmutable::now(), 'never_heard_of', null)->fields[0]->text)->toBe('Unknown error');
});

it('posts one discord embed that pings nobody', function (): void {
    Http::fake(['discord.com/*' => Http::response(null, 204)]);

    $report = app(DiscordSender::class)->send(discordUrl(), downMessage());

    expect($report->accepted)->toBeTrue();
    Http::assertSent(fn (Request $request): bool => $request->url() === DISCORD_TEST_URL && $request->method() === 'POST' && $request->data() === [
        'username' => 'Uply',
        'allowed_mentions' => ['parse' => []],
        'embeds' => [[
            'title' => '@everyone <b>Billing</b> & co is down',
            'description' => 'https://billing.example.com/health?a=1&b=2',
            'color' => 15874873,
            'timestamp' => '2026-10-28T20:54:51Z',
            'fields' => [
                ['name' => 'Cause', 'value' => 'HTTP 500', 'inline' => true],
                ['name' => 'Started', 'value' => '<t:1793220891:f>', 'inline' => true],
            ],
            'url' => 'http://localhost:5173/incidents/01incident',
        ]],
    ]);
});

it('cuts discord texts to the embed limits', function (): void {
    $long = new MonitorView('01m', '01o', str_repeat('n', 400), 'https://example.com/'.str_repeat('p', 5000));
    $payload = app(DiscordSender::class)->payload(app(AlertMessageBuilder::class)->down($long, '01i', CarbonImmutable::now(), 'timeout', null));

    expect(mb_strlen($payload['embeds'][0]['title']))->toBe(256)
        ->and(mb_strlen($payload['embeds'][0]['description']))->toBe(4096);
});

it('posts a slack message with escaped user texts and reader time zones', function (): void {
    Http::fake(['hooks.slack.com/*' => Http::response('ok', 200)]);

    $report = app(SlackSender::class)->send(slackUrl(), downMessage());

    expect($report->accepted)->toBeTrue();
    Http::assertSent(fn (Request $request): bool => $request->url() === SLACK_TEST_URL && $request->data() === [
        'text' => '@everyone &lt;b&gt;Billing&lt;/b&gt; &amp; co is down',
        'attachments' => [[
            'color' => '#f23b39',
            'blocks' => [
                ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => "*@everyone &lt;b&gt;Billing&lt;/b&gt; &amp; co is down*\nhttps://billing.example.com/health?a=1&amp;b=2"]],
                ['type' => 'section', 'fields' => [
                    ['type' => 'mrkdwn', 'text' => "*Cause*\nHTTP 500"],
                    ['type' => 'mrkdwn', 'text' => "*Started*\n<!date^1793220891^{date_short_pretty} {time_secs}|2026-10-28 20:54:51 UTC>"],
                ]],
                ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => '<http://localhost:5173/incidents/01incident|Open the incident>']],
            ],
        ]],
    ]);
});

it('reports a discord 429 with its delay and a 404 that disables the channel', function (): void {
    Http::fake(['discord.com/*' => Http::sequence()
        ->push(['message' => 'You are being rate limited.', 'retry_after' => 1.5, 'global' => false], 429)
        ->push(['message' => 'Unknown Webhook', 'code' => 10015], 404)
        ->push('upstream down', 502)]);
    $sender = app(DiscordSender::class);
    $policy = app(AlertingSettings::class)->delivery->retryPolicy();

    $limited = $sender->send(discordUrl(), downMessage());
    $rejected = $sender->send(discordUrl(), downMessage());
    $broken = $sender->send(discordUrl(), downMessage());

    expect($limited->httpStatus)->toBe(429)
        ->and($limited->retryAfterSeconds)->toBe(2)
        ->and(DeliveryRules::decide($limited, 1, $policy)->retryInSeconds)->toBe(2)
        ->and(DeliveryRules::decide($rejected, 1, $policy)->disablesChannel)->toBeTrue()
        ->and(DeliveryRules::decide($rejected, 1, $policy)->lastError)->toBe('HTTP 404 Unknown Webhook')
        ->and(DeliveryRules::decide($broken, 1, $policy)->outcome)->toBe(DecisionOutcome::Retry)
        ->and(DeliveryRules::decide($broken, 1, $policy)->retryInSeconds)->toBe(10);
});

it('reports a slack rejection and a retry after header', function (): void {
    Http::fake(['hooks.slack.com/*' => Http::sequence()
        ->push('no_active_hooks', 410)
        ->push('rate_limited', 429, ['Retry-After' => '30'])]);
    $sender = app(SlackSender::class);

    $gone = $sender->send(slackUrl(), downMessage());
    $limited = $sender->send(slackUrl(), downMessage());

    expect($gone->httpStatus)->toBe(410)
        ->and($gone->diagnostic)->toBe('no_active_hooks')
        ->and($limited->retryAfterSeconds)->toBe(30);
});

it('never keeps a url in a diagnostic', function (): void {
    Http::fake(['discord.com/*' => Http::response('bad request for '.DISCORD_TEST_URL, 400)]);

    $report = app(DiscordSender::class)->send(discordUrl(), downMessage());

    expect($report->diagnostic)->toBe('bad request for [url]')
        ->and($report->diagnostic)->not->toContain('discord-token');
});

it('reports a lost connection as a transport error without the url', function (): void {
    Http::fake(['discord.com/*' => Http::failedConnection()]);

    $report = app(DiscordSender::class)->send(discordUrl(), downMessage());

    expect($report->httpStatus)->toBeNull()
        ->and($report->diagnostic)->toBe('connection error');
});

it('mails the alert to every recipient', function (): void {
    Mail::fake();

    $report = app(MailSender::class)->send(EmailRecipients::parse(['ops@example.com', 'oncall@example.com'], 1, 10), downMessage());

    expect($report->accepted)->toBeTrue();
    Mail::assertSent(AlertMail::class, fn (AlertMail $mail): bool => $mail->hasTo('ops@example.com')
        && $mail->hasTo('oncall@example.com')
        && $mail->alert->title === '@everyone <b>Billing</b> & co is down');
});

it('renders the alert mail in markdown with escaped user texts', function (): void {
    $mail = new AlertMail(downMessage());
    $html = $mail->render();

    $mail->assertHasSubject('@everyone <b>Billing</b> & co is down');
    expect($html)->toContain('@everyone &lt;b&gt;Billing&lt;/b&gt; &amp; co is down')
        ->and($html)->not->toContain('<b>Billing</b>')
        ->and($html)->toContain('HTTP 500')
        ->and($html)->toContain('2026-10-28 20:54:51 UTC')
        ->and($html)->toContain('http://localhost:5173/incidents/01incident')
        ->and($html)->toContain('Open the incident');
});
