<?php

declare(strict_types=1);

namespace App\Alerting\Enums;

enum DeliveryKind: string
{
    case Down = 'down';
    case Up = 'up';
    case Test = 'test';
    case CertificateExpiring = 'certificate_expiring';
    case Reminder = 'reminder';

    public function hasIncident(): bool
    {
        return match ($this) {
            self::Down, self::Up, self::Reminder => true,
            self::Test, self::CertificateExpiring => false,
        };
    }
}
