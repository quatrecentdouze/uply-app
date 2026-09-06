<?php

declare(strict_types=1);

use App\Providers\AlertingServiceProvider;
use App\Providers\IncidentsServiceProvider;
use App\Providers\MonitoringServiceProvider;
use App\Providers\SharedServiceProvider;

return [
    SharedServiceProvider::class,
    MonitoringServiceProvider::class,
    IncidentsServiceProvider::class,
    AlertingServiceProvider::class,
];
