<?php

declare(strict_types=1);

namespace App\Monitoring\Enums;

enum DraftConflict: string
{
    case BodyWithoutBodyMethod = 'request_body';

    public function field(): string
    {
        return $this->value;
    }

    public function messageKey(): string
    {
        return 'monitoring.validation.conflict.'.$this->value;
    }
}
