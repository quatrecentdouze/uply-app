<?php

declare(strict_types=1);

namespace App\Monitoring\Contracts;

interface MonitorDirectory
{
    public function find(string $monitorId): ?MonitorView;
}
