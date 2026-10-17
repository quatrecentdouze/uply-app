<?php

declare(strict_types=1);

use App\Monitoring\Values\MonitorRequest;
use App\Monitoring\Values\RequestHeader;

it('keeps the headers in their order and names them', function (): void {
    $request = new MonitorRequest([new RequestHeader('Authorization', 'Bearer abc'), new RequestHeader('X-Trace', '1')], '{"ping":true}');

    expect($request->headerNames())->toBe(['Authorization', 'X-Trace'])
        ->and($request->hasBody())->toBeTrue()
        ->and($request->isEmpty())->toBeFalse()
        ->and($request->secrets())->toBe(['Bearer abc', '1', '{"ping":true}']);
});

it('refuses two headers with the same name ignoring case', function (): void {
    expect(fn () => new MonitorRequest([new RequestHeader('X-Key', 'a'), new RequestHeader('x-key', 'b')], null))
        ->toThrow(InvalidArgumentException::class, 'unique');
});

it('refuses a header name that is not an http token', function (string $name): void {
    expect(fn () => new RequestHeader($name, 'value'))->toThrow(InvalidArgumentException::class, 'token');
})->with(['X Key', 'X-Key:', '', 'Clé', "X-Key\r\n"]);

it('refuses a header value with a line break or a control character, without echoing it', function (string $value): void {
    try {
        new RequestHeader('X-Key', $value);
        $this->fail('the value was accepted');
    } catch (InvalidArgumentException $exception) {
        expect($exception->getMessage())->not->toContain('secret');
    }
})->with(["secret\r\nHost: evil", "secret\n", "secret\t", "secret\x00", "secret\x7F"]);

it('accepts printable text beyond ascii in a value', function (): void {
    expect((new RequestHeader('X-Name', 'Café ünïcode'))->value)->toBe('Café ünïcode');
});

it('treats an empty body as no body', function (): void {
    expect((new MonitorRequest([], ''))->hasBody())->toBeFalse()
        ->and((new MonitorRequest([], ''))->isEmpty())->toBeTrue();
});

it('stores nothing when there is nothing to send', function (): void {
    expect(MonitorRequest::none()->toStored())->toBeNull()
        ->and(MonitorRequest::fromStored(null)->isEmpty())->toBeTrue();
});

it('reads back what it stored', function (): void {
    $request = new MonitorRequest([new RequestHeader('Content-Type', 'application/json')], '{"a":1}');
    $read = MonitorRequest::fromStored($request->toStored());

    expect($read->sameAs($request))->toBeTrue()
        ->and($read->headerNames())->toBe(['Content-Type'])
        ->and($read->body)->toBe('{"a":1}');
});

it('replaces the headers or the body alone', function (): void {
    $request = new MonitorRequest([new RequestHeader('X-Key', 'a')], 'body');

    expect($request->withHeaders([])->headerNames())->toBe([])
        ->and($request->withHeaders([])->body)->toBe('body')
        ->and($request->withBody(null)->headerNames())->toBe(['X-Key'])
        ->and($request->withBody(null)->hasBody())->toBeFalse()
        ->and($request->sameAs($request->withBody('other')))->toBeFalse();
});
