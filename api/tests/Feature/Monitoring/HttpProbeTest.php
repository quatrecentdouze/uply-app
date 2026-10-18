<?php

declare(strict_types=1);

use App\Monitoring\Enums\FailureKind;
use App\Monitoring\Enums\HttpMethod;
use App\Monitoring\Probe\DnsTargetGuard;
use App\Monitoring\Probe\HttpProbe;
use App\Monitoring\Probe\SystemHostResolver;
use App\Monitoring\Probe\TargetGuard;
use App\Monitoring\Settings\MonitoringSettings;
use App\Monitoring\Settings\ProbeSettings;
use App\Monitoring\Values\MonitorRequest;
use App\Monitoring\Values\PinnedTarget;
use App\Monitoring\Values\ProbeRequest;
use App\Monitoring\Values\ProbeResult;
use App\Monitoring\Values\RequestHeader;
use Illuminate\Http\Client\Factory;
use Tests\Support\ProbeTargetServer;
use Tests\Support\StaticHostResolver;

beforeAll(fn () => ProbeTargetServer::boot());

afterAll(fn () => ProbeTargetServer::shutdown());

function probeSettings(bool $allowPrivateTargets): ProbeSettings
{
    $shipped = app(MonitoringSettings::class)->probe;

    return new ProbeSettings(
        $shipped->defaultTimeoutSeconds,
        $shipped->maxTimeoutSeconds,
        $shipped->connectTimeoutSeconds,
        $shipped->maxRedirects,
        $shipped->bodyReadCapBytes,
        $shipped->userAgent,
        $allowPrivateTargets,
    );
}

function probeTarget(string $path, int $timeoutSeconds = 5, bool $followRedirects = true, HttpMethod $method = HttpMethod::Get, ?TargetGuard $guard = null, ?MonitorRequest $request = null): ProbeResult
{
    $settings = probeSettings(true);
    $probe = new HttpProbe(new Factory, $guard ?? new DnsTargetGuard(new SystemHostResolver, $settings), $settings);

    return $probe->run(new ProbeRequest(ProbeTargetServer::boot()->url($path), $method, $timeoutSeconds, $followRedirects, $request ?? MonitorRequest::none()));
}

function recordedBy(string $path, HttpMethod $method, MonitorRequest $request): array
{
    $id = strtolower((string) str()->ulid());
    $guard = new DnsTargetGuard(new StaticHostResolver(['localhost' => ['127.0.0.1']]), probeSettings(true));
    $result = probeTarget(str_replace('{id}', $id, $path), method: $method, guard: $guard, request: $request);
    $file = ProbeTargetServer::recordFile($id);
    $record = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    if (is_file($file)) {
        unlink($file);
    }

    return ['status' => $result->httpStatus, ...(is_array($record) ? $record : [])];
}

function signedRequest(): MonitorRequest
{
    return new MonitorRequest([
        new RequestHeader('Authorization', 'Bearer probe-secret'),
        new RequestHeader('Content-Type', 'application/json'),
        new RequestHeader('User-Agent', 'Custom/2.0'),
    ], '{"probe":"probe-body"}');
}

function guardOpenOnlyFor(string $firstUrl): TargetGuard
{
    $open = new DnsTargetGuard(new SystemHostResolver, probeSettings(true));
    $strict = new DnsTargetGuard(new SystemHostResolver, probeSettings(false));

    return new readonly class($open, $strict, $firstUrl) implements TargetGuard
    {
        public function __construct(private TargetGuard $open, private TargetGuard $strict, private string $firstUrl) {}

        public function pin(string $url): PinnedTarget
        {
            return $url === $this->firstUrl ? $this->open->pin($url) : $this->strict->pin($url);
        }
    };
}

it('reports a 200 with its timings', function (): void {
    $result = probeTarget('/status/200');

    expect($result->httpStatus)->toBe(200)
        ->and($result->failureKind)->toBeNull()
        ->and($result->timings->connectMs)->toBeInt()
        ->and($result->timings->ttfbMs)->toBeInt()
        ->and($result->timings->tlsMs)->toBeNull()
        ->and($result->timings->totalMs)->toBeInt();
});

it('reports a 500 as a response for the rules to judge', function (): void {
    $result = probeTarget('/status/500');

    expect($result->httpStatus)->toBe(500)
        ->and($result->failureKind)->toBeNull();
});

it('ends a slow response with a timeout', function (): void {
    $result = probeTarget('/slow', timeoutSeconds: 1);

    expect($result->failureKind)->toBe(FailureKind::Timeout)
        ->and($result->httpStatus)->toBeNull()
        ->and($result->timings->totalMs)->toBeGreaterThanOrEqual(900)->toBeLessThan(1900);
});

it('follows redirects by hand', function (): void {
    expect(probeTarget('/redirect-to-ok')->httpStatus)->toBe(200)
        ->and(probeTarget('/redirect-to-ok', followRedirects: false)->httpStatus)->toBe(302);
});

it('stops a redirect loop', function (): void {
    $result = probeTarget('/redirect-loop');

    expect($result->failureKind)->toBe(FailureKind::TooManyRedirects)
        ->and($result->httpStatus)->toBe(302);
});

it('refuses a redirect to a private address when private targets are forbidden', function (): void {
    $firstUrl = ProbeTargetServer::boot()->url('/redirect-private');
    $result = probeTarget('/redirect-private', guard: guardOpenOnlyFor($firstUrl));

    expect($result->failureKind)->toBe(FailureKind::BlockedTarget)
        ->and($result->errorMessage)->toContain('192.168.1.10');
});

it('refuses a redirect to the metadata address even when private targets are allowed', function (): void {
    expect(probeTarget('/redirect-metadata')->failureKind)->toBe(FailureKind::BlockedTarget);
});

it('refuses the first hop before connecting when private targets are forbidden', function (): void {
    $settings = probeSettings(false);
    $probe = new HttpProbe(new Factory, new DnsTargetGuard(new SystemHostResolver, $settings), $settings);

    $result = $probe->run(new ProbeRequest(ProbeTargetServer::boot()->url('/status/200'), HttpMethod::Get, 5, true, MonitorRequest::none()));

    expect($result->failureKind)->toBe(FailureKind::BlockedTarget)
        ->and($result->httpStatus)->toBeNull();
});

it('stops reading a body larger than the cap', function (string $path): void {
    $result = probeTarget($path);

    expect($result->failureKind)->toBe(FailureKind::BodyTooLarge)
        ->and($result->httpStatus)->toBe(200);
})->with(['/big', '/big-declared']);

it('reports a refused connection', function (): void {
    $settings = probeSettings(true);
    $probe = new HttpProbe(new Factory, new DnsTargetGuard(new SystemHostResolver, $settings), $settings);

    $result = $probe->run(new ProbeRequest('http://127.0.0.1:'.ProbeTargetServer::freePort().'/', HttpMethod::Get, 5, true, MonitorRequest::none()));

    expect($result->failureKind)->toBe(FailureKind::Connect);
});

it('sends head requests', function (): void {
    expect(probeTarget('/status/204', method: HttpMethod::Head)->httpStatus)->toBe(204);
});

it('resolves localhost through the system resolver', function (): void {
    $addresses = (new SystemHostResolver)->resolve('localhost')->addresses;

    expect(array_intersect($addresses, ['127.0.0.1', '::1']))->not->toBeEmpty();
});

it('sends the method, the headers and the body of the monitor', function (HttpMethod $method): void {
    $record = recordedBy('/record/{id}', $method, signedRequest());

    expect($record['status'])->toBe(200)
        ->and($record['method'])->toBe($method->verb())
        ->and($record['headers']['authorization'] ?? null)->toBe('Bearer probe-secret')
        ->and($record['headers']['content-type'] ?? null)->toBe('application/json')
        ->and($record['headers']['user-agent'] ?? null)->toBe('Custom/2.0')
        ->and($record['body'])->toBe('{"probe":"probe-body"}');
})->with([HttpMethod::Post, HttpMethod::Put, HttpMethod::Patch]);

it('sends no body and no content type of its own with get', function (): void {
    $record = recordedBy('/record/{id}', HttpMethod::Get, MonitorRequest::none());

    expect($record['method'])->toBe('GET')
        ->and($record['headers'])->not->toHaveKey('content-type')
        ->and($record['headers']['user-agent'] ?? null)->toBe('Uply/1.0')
        ->and($record['body'])->toBe('');
});

it('follows 301, 302 and 303 with a get without body', function (int $status): void {
    $record = recordedBy("/hop/{$status}/{id}", HttpMethod::Post, signedRequest());

    expect($record['method'])->toBe('GET')
        ->and($record['body'])->toBe('')
        ->and($record['headers']['authorization'] ?? null)->toBe('Bearer probe-secret');
})->with([301, 302, 303]);

it('follows 307 and 308 with the method and the body', function (int $status): void {
    $record = recordedBy("/hop/{$status}/{id}", HttpMethod::Put, signedRequest());

    expect($record['method'])->toBe('PUT')
        ->and($record['body'])->toBe('{"probe":"probe-body"}')
        ->and($record['headers']['authorization'] ?? null)->toBe('Bearer probe-secret');
})->with([307, 308]);

it('drops the headers and the body of the monitor on a hop to another origin', function (): void {
    $record = recordedBy('/hop-away/307/{id}', HttpMethod::Post, signedRequest());

    expect($record['status'])->toBe(200)
        ->and($record['method'])->toBe('POST')
        ->and($record['body'])->toBe('')
        ->and($record['headers'])->not->toHaveKey('authorization')
        ->and($record['headers']['user-agent'] ?? null)->toBe('Uply/1.0');
});

it('keeps the secrets of the monitor out of a diagnostic', function (): void {
    $settings = probeSettings(false);
    $probe = new HttpProbe(new Factory, new DnsTargetGuard(new SystemHostResolver, $settings), $settings);
    $request = new MonitorRequest([new RequestHeader('X-Token', 'probe-path-secret')], null);

    $result = $probe->run(new ProbeRequest('relative/probe-path-secret', HttpMethod::Get, 5, true, $request));

    expect($result->failureKind)->toBe(FailureKind::BlockedTarget)
        ->and($result->errorMessage)->not->toContain('probe-path-secret')
        ->and($result->errorMessage)->toContain('[secret]');
});
