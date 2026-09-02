<?php

declare(strict_types=1);

use App\Monitoring\Console\MaintainCheckStorageCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(MaintainCheckStorageCommand::class)->hourly()->withoutOverlapping()->onOneServer();
