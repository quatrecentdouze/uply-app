<?php

declare(strict_types=1);

namespace App\Monitoring\Enums;

enum UrlProblem: string
{
    case Scheme = 'scheme';
    case Malformed = 'malformed';
    case BlockedAddress = 'blocked_address';

    public function messageKey(): string
    {
        return 'monitoring.validation.url.'.$this->value;
    }
}
