<?php

declare(strict_types=1);

use App\Providers\MonitoringServiceProvider;
use App\Providers\SharedServiceProvider;

return [
    SharedServiceProvider::class,
    MonitoringServiceProvider::class,
];
