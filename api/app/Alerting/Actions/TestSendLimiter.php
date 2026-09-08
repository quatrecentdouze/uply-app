<?php

declare(strict_types=1);

namespace App\Alerting\Actions;

use App\Alerting\Settings\AlertingSettings;
use Illuminate\Cache\RateLimiter;

final readonly class TestSendLimiter
{
    private const string KEY_PREFIX = 'alert-test:';

    private const int WINDOW_SECONDS = 60;

    public function __construct(
        private RateLimiter $limiter,
        private AlertingSettings $settings,
    ) {}

    public function secondsUntilAllowed(string $channelId): ?int
    {
        $key = self::KEY_PREFIX.$channelId;
        if ($this->limiter->tooManyAttempts($key, $this->settings->delivery->testSendsPerMinute)) {
            return max(1, $this->limiter->availableIn($key));
        }
        $this->limiter->hit($key, self::WINDOW_SECONDS);

        return null;
    }
}
