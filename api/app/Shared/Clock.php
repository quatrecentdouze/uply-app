<?php

declare(strict_types=1);

namespace App\Shared;

use Carbon\CarbonImmutable;

interface Clock
{
    public function now(): CarbonImmutable;
}
