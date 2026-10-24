<?php

declare(strict_types=1);

use App\Maintenance\Enums\MaintenanceError;
use App\Maintenance\Enums\PeriodProblem;

it('answers every maintenance error with a conflict and its own code', function (MaintenanceError $error): void {
    expect($error->httpStatus())->toBe(409)
        ->and($error->code())->toBe($error->value);
})->with(MaintenanceError::cases());

it('has a text for every maintenance error, period problem and validation message', function (string $key): void {
    expect(__($key))->toBeString()->not->toBe($key);
})->with([
    ...array_map(fn (MaintenanceError $error): string => $error->messageKey(), MaintenanceError::cases()),
    ...array_map(fn (PeriodProblem $problem): string => "maintenance.validation.{$problem->value}", PeriodProblem::cases()),
    'maintenance.validation.monitor',
]);
