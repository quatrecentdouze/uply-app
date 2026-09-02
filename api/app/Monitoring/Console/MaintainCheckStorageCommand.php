<?php

declare(strict_types=1);

namespace App\Monitoring\Console;

use App\Monitoring\Storage\CheckStorageMaintenance;
use App\Shared\Clock;
use Illuminate\Console\Command;

final class MaintainCheckStorageCommand extends Command
{
    protected $signature = 'monitoring:maintain';

    protected $description = 'Create the coming check result partitions, drop the expired ones and purge old hourly stats';

    public function handle(CheckStorageMaintenance $maintenance, Clock $clock): int
    {
        $report = $maintenance->run($clock->now());
        $this->line("partitions_created={$report->partitionsCreated} partitions_dropped={$report->partitionsDropped} hourly_stats_purged={$report->hourlyStatsPurged}");

        return self::SUCCESS;
    }
}
