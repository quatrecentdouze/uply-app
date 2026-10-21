<?php

declare(strict_types=1);

use App\Monitoring\Enums\HttpMethod;

it('sends a body with post, put and patch only', function (HttpMethod $method, bool $sendsBody): void {
    expect($method->sendsBody())->toBe($sendsBody);
})->with([
    [HttpMethod::Get, false],
    [HttpMethod::Head, false],
    [HttpMethod::Post, true],
    [HttpMethod::Put, true],
    [HttpMethod::Patch, true],
]);

it('gets no response body with head only', function (): void {
    expect(array_map(fn (HttpMethod $method): bool => $method->receivesBody(), HttpMethod::cases()))->toBe([true, false, true, true, true]);
});

it('names the verb in capitals', function (): void {
    expect(HttpMethod::Patch->verb())->toBe('PATCH');
});
