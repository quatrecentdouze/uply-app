<?php

declare(strict_types=1);

namespace App\Alerting\Rules;

use App\Alerting\Values\DeliveryDecision;
use App\Alerting\Values\RetryPolicy;
use App\Alerting\Values\SendReport;

final class DeliveryRules
{
    private const int FIRST_SUCCESS_STATUS = 200;

    private const int FIRST_REDIRECT_STATUS = 300;

    private const int TOO_MANY_REQUESTS = 429;

    private const array REJECTING_STATUSES = [401, 403, 404, 410];

    private const int FIRST_SERVER_ERROR_STATUS = 500;

    private const int FIRST_INVALID_STATUS = 600;

    public static function decide(SendReport $report, int $attemptsMade, RetryPolicy $policy): DeliveryDecision
    {
        $status = $report->httpStatus;
        if ($report->accepted || ($status !== null && $status >= self::FIRST_SUCCESS_STATUS && $status < self::FIRST_REDIRECT_STATUS)) {
            return DeliveryDecision::sent();
        }
        $lastError = self::lastError($report);
        if ($report->permanent) {
            return DeliveryDecision::failed($lastError);
        }
        if ($status === self::TOO_MANY_REQUESTS) {
            return self::retryOrFail($attemptsMade, $policy, $lastError, $report->retryAfterSeconds);
        }
        if (in_array($status, self::REJECTING_STATUSES, true)) {
            return DeliveryDecision::failed($lastError, disablesChannel: true);
        }
        if ($status === null || ($status >= self::FIRST_SERVER_ERROR_STATUS && $status < self::FIRST_INVALID_STATUS)) {
            return self::retryOrFail($attemptsMade, $policy, $lastError, null);
        }

        return DeliveryDecision::failed($lastError);
    }

    public static function retryAfterSeconds(mixed $bodyRetryAfter, ?string $headerRetryAfter): ?int
    {
        if (is_int($bodyRetryAfter) || is_float($bodyRetryAfter)) {
            return max(1, (int) ceil($bodyRetryAfter));
        }
        if ($headerRetryAfter !== null && preg_match('/^\s*(\d+)\s*$/', $headerRetryAfter, $matches) === 1) {
            return max(1, (int) $matches[1]);
        }

        return null;
    }

    private static function retryOrFail(int $attemptsMade, RetryPolicy $policy, string $lastError, ?int $retryAfterSeconds): DeliveryDecision
    {
        if ($attemptsMade >= $policy->maxAttempts) {
            return DeliveryDecision::failed($lastError);
        }

        return DeliveryDecision::retry($retryAfterSeconds ?? $policy->delayAfter($attemptsMade), $lastError);
    }

    private static function lastError(SendReport $report): string
    {
        $text = $report->httpStatus === null ? $report->diagnostic : trim("HTTP {$report->httpStatus} {$report->diagnostic}");

        return MessageFormatRules::cut($text, DeliveryDecision::MAX_LAST_ERROR_LENGTH);
    }
}
