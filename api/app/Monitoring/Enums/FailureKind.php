<?php

declare(strict_types=1);

namespace App\Monitoring\Enums;

enum FailureKind: string
{
    case Timeout = 'timeout';
    case Dns = 'dns';
    case Connect = 'connect';
    case Tls = 'tls';
    case HttpStatus = 'http_status';
    case TooManyRedirects = 'too_many_redirects';
    case BlockedTarget = 'blocked_target';
    case BodyTooLarge = 'body_too_large';
    case Unknown = 'unknown';
}
