<?php

declare(strict_types=1);

use App\StatusPages\Enums\StatusPageError;

it('maps each status page error to its status', function (StatusPageError $error, int $status): void {
    expect($error->httpStatus())->toBe($status)
        ->and($error->code())->toBe($error->value);
})->with([
    [StatusPageError::StatusPageLimitReached, 409],
    [StatusPageError::SlugTaken, 409],
]);

it('has a text for every status page error and validation message', function (string $key): void {
    expect(__($key))->toBeString()->not->toBe($key);
})->with([
    ...array_map(fn (StatusPageError $error): string => $error->messageKey(), StatusPageError::cases()),
    'status_pages.validation.slug',
    'status_pages.validation.slug_taken',
    'status_pages.validation.monitor',
]);
