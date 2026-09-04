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
use App\Monitoring\Values\PinnedTarget;
use App\Monitoring\Values\ProbeRequest;
use App\Monitoring\Values\ProbeResult;
use Illuminate\Http\Client\Factory;
use Tests\Support\ProbeTargetServer;

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

function probeTarget(string $path, int $timeoutSeconds = 5, bool $followRedirects = true, HttpMethod $method = HttpMethod::Get, ?TargetGuard $guard = null): ProbeResult
{
    $settings = probeSettings(true);
    $probe = new HttpProbe(new Factory, $guard ?? new DnsTargetGuard(new SystemHostResolver, $settings), $settings);

    return $probe->run(new ProbeRequest(ProbeTargetServer::boot()->url($path), $method, $timeoutSeconds, $followRedirects));
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

    $result = $probe->run(new ProbeRequest(ProbeTargetServer::boot()->url('/status/200'), HttpMethod::Get, 5, true));

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

    $result = $probe->run(new ProbeRequest('http://127.0.0.1:'.ProbeTargetServer::freePort().'/', HttpMethod::Get, 5, true));

    expect($result->failureKind)->toBe(FailureKind::Connect);
});

it('sends head requests', function (): void {
    expect(probeTarget('/status/204', method: HttpMethod::Head)->httpStatus)->toBe(204);
});

it('resolves localhost through the system resolver', function (): void {
    $addresses = (new SystemHostResolver)->resolve('localhost')->addresses;

    expect(array_intersect($addresses, ['127.0.0.1', '::1']))->not->toBeEmpty();
});
