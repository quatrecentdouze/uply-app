<?php

declare(strict_types=1);

namespace App\Alerting\Console;

use App\Alerting\Actions\SendTestAlert;
use App\Alerting\Enums\TestAlertStatus;
use App\Shared\Clock;
use Illuminate\Console\Command;

final class SendTestAlertCommand extends Command
{
    protected $signature = 'alerting:test {channel}';

    protected $description = 'Queue a test alert for one alert channel';

    public function handle(SendTestAlert $sendTestAlert, Clock $clock): int
    {
        $outcome = $sendTestAlert($this->argument('channel'), $clock);

        if ($outcome->status === TestAlertStatus::Queued) {
            $this->line("status=queued delivery={$outcome->deliveryId}");

            return self::SUCCESS;
        }
        $this->line($outcome->status === TestAlertStatus::Limited
            ? "status=limited retry_in_seconds={$outcome->retryInSeconds}"
            : "status={$outcome->status->value}");

        return self::FAILURE;
    }
}
