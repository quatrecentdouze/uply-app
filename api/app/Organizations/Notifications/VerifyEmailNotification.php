<?php

declare(strict_types=1);

namespace App\Organizations\Notifications;

use App\Shared\QueueName;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

final class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue(QueueName::Default->value);
        $this->afterCommit();
    }
}
