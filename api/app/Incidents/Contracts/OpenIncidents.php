<?php

declare(strict_types=1);

namespace App\Incidents\Contracts;

interface OpenIncidents
{
    public function all(): array;
}
