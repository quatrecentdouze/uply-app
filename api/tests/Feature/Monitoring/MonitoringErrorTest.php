<?php

declare(strict_types=1);

use App\Monitoring\Enums\MonitoringError;

it('maps each monitoring error to its status', function (MonitoringError $error, int $status): void {
    expect($error->httpStatus())->toBe($status)
        ->and($error->code())->toBe($error->value);
})->with([
    [MonitoringError::MonitorLimitReached, 409],
]);

it('has a text for every monitoring error', function (MonitoringError $error): void {
    expect(__($error->messageKey()))->toBeString()->not->toBe($error->messageKey());
})->with(MonitoringError::cases());
