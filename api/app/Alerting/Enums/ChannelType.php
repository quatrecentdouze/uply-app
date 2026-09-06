<?php

declare(strict_types=1);

namespace App\Alerting\Enums;

enum ChannelType: string
{
    case Email = 'email';
    case Discord = 'discord';
    case Slack = 'slack';

    public function usesWebhook(): bool
    {
        return $this !== self::Email;
    }
}
