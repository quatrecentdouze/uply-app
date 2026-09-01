<?php

declare(strict_types=1);

namespace App\Monitoring\Enums;

enum HttpMethod: string
{
    case Get = 'get';
    case Head = 'head';

    public function verb(): string
    {
        return strtoupper($this->value);
    }
}
