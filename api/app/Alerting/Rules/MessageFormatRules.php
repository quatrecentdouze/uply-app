<?php

declare(strict_types=1);

namespace App\Alerting\Rules;

use Carbon\CarbonImmutable;

final class MessageFormatRules
{
    private const string ELLIPSIS = '…';

    private const array SLACK_TEXT_ESCAPES = ['&' => '&amp;', '<' => '&lt;', '>' => '&gt;'];

    private const array SLACK_URL_ESCAPES = ['&' => '&amp;', '<' => '%3C', '>' => '%3E', '|' => '%7C'];

    private const string SLACK_DATE_FORMAT = '{date_short_pretty} {time_secs}';

    private const string DISCORD_TIMESTAMP_STYLE = 'f';

    private const string MARKDOWN_SPECIALS_PATTERN = '/([\\\\`*_{}\[\]()#+\-.!|~])/';

    public static function cut(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, max(0, $maxLength - mb_strlen(self::ELLIPSIS))).self::ELLIPSIS;
    }

    public static function slackEscape(string $text): string
    {
        return strtr($text, self::SLACK_TEXT_ESCAPES);
    }

    public static function slackDate(CarbonImmutable $at, string $fallback): string
    {
        return '<!date^'.$at->getTimestamp().'^'.self::SLACK_DATE_FORMAT.'|'.self::slackEscape($fallback).'>';
    }

    public static function slackLink(string $url, string $label): string
    {
        return '<'.strtr($url, self::SLACK_URL_ESCAPES).'|'.self::slackEscape($label).'>';
    }

    public static function discordTimestamp(CarbonImmutable $at): string
    {
        return '<t:'.$at->getTimestamp().':'.self::DISCORD_TIMESTAMP_STYLE.'>';
    }

    public static function markdownEscape(string $text): string
    {
        return (string) preg_replace(self::MARKDOWN_SPECIALS_PATTERN, '\\\\$1', $text);
    }
}
