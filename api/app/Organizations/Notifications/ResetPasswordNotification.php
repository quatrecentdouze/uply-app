<?php

declare(strict_types=1);

namespace App\Organizations\Notifications;

use App\Shared\QueueName;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use SensitiveParameter;

final class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public function __construct(#[SensitiveParameter] string $token)
    {
        parent::__construct($token);
        $this->onQueue(QueueName::Default->value);
        $this->afterCommit();
    }
}
