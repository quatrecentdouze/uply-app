<?php

declare(strict_types=1);

namespace App\Monitoring\Console;

use App\Monitoring\Jobs\RunCheckJob;
use App\Monitoring\Settings\MonitoringSettings;
use App\Monitoring\Storage\DueMonitorClaimer;
use App\Monitoring\Values\ClaimedCheck;
use App\Shared\Clock;
use Illuminate\Console\Command;
use Illuminate\Contracts\Bus\Dispatcher;

final class DispatchChecksCommand extends Command
{
    protected $signature = 'checks:dispatch';

    protected $description = 'Claim the due monitors and queue one check job for each';

    public function handle(DueMonitorClaimer $claimer, Dispatcher $bus, Clock $clock, MonitoringSettings $settings): int
    {
        $claimed = $claimer->claim($clock->now(), $settings->dispatch->batchSize);
        foreach ($claimed as $check) {
            if ($check instanceof ClaimedCheck) {
                $bus->dispatch(new RunCheckJob($check->monitorId, $check->scheduledAt));
            }
        }
        $this->line('dispatched='.count($claimed));

        return self::SUCCESS;
    }
}
