<?php

declare(strict_types=1);

use App\Alerting\Console\SendRemindersCommand;
use App\Monitoring\Console\DispatchChecksCommand;
use App\Monitoring\Console\MaintainCheckStorageCommand;
use App\Monitoring\Settings\DispatchTick;
use App\Monitoring\Settings\MonitoringSettings;
use Illuminate\Support\Facades\Schedule;

$dispatchChecks = Schedule::command(DispatchChecksCommand::class)->withoutOverlapping()->onOneServer();
match (app(MonitoringSettings::class)->dispatch->tick) {
    DispatchTick::EverySecond => $dispatchChecks->everySecond(),
    DispatchTick::EveryTwoSeconds => $dispatchChecks->everyTwoSeconds(),
    DispatchTick::EveryFiveSeconds => $dispatchChecks->everyFiveSeconds(),
    DispatchTick::EveryTenSeconds => $dispatchChecks->everyTenSeconds(),
    DispatchTick::EveryFifteenSeconds => $dispatchChecks->everyFifteenSeconds(),
    DispatchTick::EveryTwentySeconds => $dispatchChecks->everyTwentySeconds(),
    DispatchTick::EveryThirtySeconds => $dispatchChecks->everyThirtySeconds(),
};

Schedule::command(MaintainCheckStorageCommand::class)->hourly()->withoutOverlapping()->onOneServer();

Schedule::command(SendRemindersCommand::class)->everyMinute()->withoutOverlapping()->onOneServer();
