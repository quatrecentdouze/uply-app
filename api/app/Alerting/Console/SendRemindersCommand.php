<?php

declare(strict_types=1);

namespace App\Alerting\Console;

use App\Alerting\Actions\SendReminders;
use Illuminate\Console\Command;

final class SendRemindersCommand extends Command
{
    protected $signature = 'alerts:remind';

    protected $description = 'Queue the reminders that are due for the open incidents';

    public function handle(SendReminders $sendReminders): int
    {
        $this->line('queued='.$sendReminders());

        return self::SUCCESS;
    }
}
