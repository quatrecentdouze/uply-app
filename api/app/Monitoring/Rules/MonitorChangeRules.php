<?php

declare(strict_types=1);

namespace App\Monitoring\Rules;

use App\Monitoring\Values\CheckDefinition;
use App\Monitoring\Values\MonitorChangePlan;
use Carbon\CarbonImmutable;

final class MonitorChangeRules
{
    public static function plan(CheckDefinition $before, CheckDefinition $after, CarbonImmutable $nextCheckAt, CarbonImmutable $now): MonitorChangePlan
    {
        $clearsStreak = ! $before->judgesLike($after);
        $resumed = $before->isPaused && ! $after->isPaused;
        $forgetsCertificate = $before->url !== $after->url;
        if ($clearsStreak || $resumed) {
            return new MonitorChangePlan($clearsStreak, $now, $forgetsCertificate);
        }
        if ($before->intervalSeconds !== $after->intervalSeconds) {
            return new MonitorChangePlan(false, $nextCheckAt->min($now->addSeconds($after->intervalSeconds)));
        }

        return new MonitorChangePlan(false, $nextCheckAt);
    }
}
