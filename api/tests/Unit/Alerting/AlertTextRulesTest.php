<?php

declare(strict_types=1);

use App\Alerting\Rules\AlertTextRules;
use App\Alerting\Rules\MessageFormatRules;
use App\Alerting\Values\TranslatableText;
use Carbon\CarbonImmutable;

function durationKeys(int $seconds): array
{
    return array_map(fn (TranslatableText $text): string => $text->key.'='.$text->parameters['count'], AlertTextRules::durationParts($seconds));
}

it('reads the cause from the failure kind', function (string $kind, ?int $status, string $key, string $statusText): void {
    $cause = AlertTextRules::cause($kind, $status);

    expect($cause->key)->toBe($key)
        ->and($cause->parameters)->toBe(['status' => $statusText])
        ->and($cause->fallbackKey)->toBe('alerting.cause.unknown');
})->with([
    'http status' => ['http_status', 500, 'alerting.cause.http_status', '500'],
    'timeout' => ['timeout', null, 'alerting.cause.timeout', ''],
    'dns' => ['dns', null, 'alerting.cause.dns', ''],
    'not a stable id' => ['../../secrets', null, 'alerting.cause.unknown', ''],
]);

it('splits a duration into its units', function (int $seconds, array $keys): void {
    expect(durationKeys($seconds))->toBe($keys);
})->with([
    'minutes and seconds' => [252, ['alerting.duration.minutes=4', 'alerting.duration.seconds=12']],
    'zero' => [0, ['alerting.duration.seconds=0']],
    'hours' => [3725, ['alerting.duration.hours=1', 'alerting.duration.minutes=2', 'alerting.duration.seconds=5']],
    'a day and an hour' => [90000, ['alerting.duration.days=1', 'alerting.duration.hours=1']],
    'negative' => [-5, ['alerting.duration.seconds=0']],
]);

it('cuts texts to a provider limit with an ellipsis', function (): void {
    expect(MessageFormatRules::cut('short', 256))->toBe('short')
        ->and(MessageFormatRules::cut(str_repeat('a', 300), 256))->toHaveLength(256)->toEndWith('…')
        ->and(mb_strlen(MessageFormatRules::cut(str_repeat('é', 2000), 1024)))->toBe(1024);
});

it('escapes slack control characters in every user text', function (): void {
    expect(MessageFormatRules::slackEscape('A & B <@here> > C'))->toBe('A &amp; B &lt;@here&gt; &gt; C')
        ->and(MessageFormatRules::slackLink('https://app.test/incidents/1?a=1&b=2', 'Open <now>'))->toBe('<https://app.test/incidents/1?a=1&amp;b=2|Open &lt;now&gt;>')
        ->and(MessageFormatRules::slackDate(CarbonImmutable::parse('2026-10-28 20:54:51', 'UTC'), '2026-10-28 20:54:51 UTC'))
        ->toBe('<!date^1793220891^{date_short_pretty} {time_secs}|2026-10-28 20:54:51 UTC>');
});

it('writes discord timestamps and escapes markdown for the mail', function (): void {
    expect(MessageFormatRules::discordTimestamp(CarbonImmutable::parse('2026-10-28 20:54:51', 'UTC')))->toBe('<t:1793220891:f>')
        ->and(MessageFormatRules::markdownEscape('[click](http://x) **bold** #1'))->toBe('\[click\]\(http://x\) \*\*bold\*\* \#1');
});
