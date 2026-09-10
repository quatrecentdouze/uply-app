<?php

declare(strict_types=1);

use App\Providers\AlertingServiceProvider;
use App\Providers\IncidentsServiceProvider;
use App\Providers\MonitoringServiceProvider;
use App\Providers\OrganizationsServiceProvider;
use App\Providers\RealtimeServiceProvider;
use App\Providers\SharedServiceProvider;

return [
    SharedServiceProvider::class,
    OrganizationsServiceProvider::class,
    MonitoringServiceProvider::class,
    IncidentsServiceProvider::class,
    AlertingServiceProvider::class,
    RealtimeServiceProvider::class,
];
