<?php

declare(strict_types=1);

use App\Monitoring\Values\AcceptedStatusCodes;
use App\Monitoring\Values\StatusCodeRange;

it('accepts codes inside any of its ranges', function (): void {
    $codes = AcceptedStatusCodes::parse(['200-299', '304']);

    expect($codes->accepts(200))->toBeTrue()
        ->and($codes->accepts(299))->toBeTrue()
        ->and($codes->accepts(304))->toBeTrue()
        ->and($codes->accepts(300))->toBeFalse()
        ->and($codes->accepts(500))->toBeFalse()
        ->and($codes->toTexts())->toBe(['200-299', '304']);
});

it('parses spaced ranges and single codes', function (): void {
    expect(StatusCodeRange::parse(' 200 - 204 ')->toText())->toBe('200-204')
        ->and(StatusCodeRange::parse('418')->toText())->toBe('418');
});

it('rejects ranges outside http or reversed', function (string $text): void {
    expect(fn () => StatusCodeRange::parse($text))->toThrow(InvalidArgumentException::class);
})->with(['99', '600', '299-200', '2xx', '', '200-']);

it('needs at least one range', function (): void {
    expect(fn () => new AcceptedStatusCodes)->toThrow(InvalidArgumentException::class);
});
