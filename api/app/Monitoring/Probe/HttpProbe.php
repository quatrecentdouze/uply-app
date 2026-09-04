<?php

declare(strict_types=1);

namespace App\Monitoring\Probe;

use App\Monitoring\Enums\FailureKind;
use App\Monitoring\Settings\ProbeSettings;
use App\Monitoring\Values\PinnedTarget;
use App\Monitoring\Values\ProbeRequest;
use App\Monitoring\Values\ProbeResult;
use App\Monitoring\Values\ProbeTimings;
use App\Monitoring\Values\TargetRefusedException;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\TransferStats;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Throwable;

final readonly class HttpProbe implements Probe
{
    private const int NANOSECONDS_PER_SECOND = 1_000_000_000;

    private const int NANOSECONDS_PER_MILLISECOND = 1_000_000;

    private const int MICROSECONDS_PER_MILLISECOND = 1_000;

    private const int MICROSECONDS_PER_SECOND = 1_000_000;

    private const array ALLOWED_PROTOCOLS = ['http', 'https'];

    private const array NO_PROXY = ['no' => ['*']];

    public function __construct(
        private Factory $http,
        private TargetGuard $guard,
        private ProbeSettings $settings,
    ) {}

    public function run(ProbeRequest $request): ProbeResult
    {
        $startedAt = hrtime(true);
        $deadline = $startedAt + $request->timeoutSeconds * self::NANOSECONDS_PER_SECOND;
        $url = $request->url;
        $redirects = 0;

        while (true) {
            try {
                $pinned = $this->guard->pin($url);
            } catch (TargetRefusedException $refusal) {
                return ProbeResult::failed($refusal->failureKind, $refusal->getMessage(), new ProbeTimings(dnsMs: $refusal->dnsMs, totalMs: $this->elapsedMs($startedAt)));
            }
            $remainingSeconds = ($deadline - hrtime(true)) / self::NANOSECONDS_PER_SECOND;
            if ($remainingSeconds <= 0) {
                return ProbeResult::failed(FailureKind::Timeout, "no time left before {$url}", new ProbeTimings(dnsMs: $pinned->dnsMs, totalMs: $this->elapsedMs($startedAt)));
            }

            $hop = new TransferRecorder;
            try {
                $response = $this->pendingRequest($pinned, $remainingSeconds, $hop)->send($request->method->verb(), $url);
            } catch (RequestException $exception) {
                $response = $exception->response;
            } catch (ConnectionException $exception) {
                $kind = $hop->bodyCapExceeded ? FailureKind::BodyTooLarge : $this->failureKind($hop->errorCode);

                return ProbeResult::failed($kind, $exception->getMessage(), $this->timings($hop, $pinned, $startedAt), $hop->httpStatus);
            } catch (Throwable $exception) {
                return ProbeResult::failed(FailureKind::Unknown, $exception->getMessage(), $this->timings($hop, $pinned, $startedAt), $hop->httpStatus);
            }

            $timings = $this->timings($hop, $pinned, $startedAt);
            if ($hop->bodyCapExceeded) {
                return ProbeResult::failed(FailureKind::BodyTooLarge, "body larger than {$this->settings->bodyReadCapBytes} bytes", $timings, $response->status());
            }
            $location = $response->header('Location');
            if (! $request->followRedirects || ! $response->redirect() || $location === '') {
                return ProbeResult::responded($response->status(), $timings);
            }
            if ($redirects >= $this->settings->maxRedirects) {
                return ProbeResult::failed(FailureKind::TooManyRedirects, "more than {$this->settings->maxRedirects} redirects", $timings, $response->status());
            }
            try {
                $url = (string) UriResolver::resolve(Utils::uriFor($url), Utils::uriFor($location));
            } catch (Throwable $exception) {
                return ProbeResult::failed(FailureKind::Unknown, $exception->getMessage(), $timings, $response->status());
            }
            $redirects++;
        }
    }

    private function pendingRequest(PinnedTarget $pinned, float $remainingSeconds, TransferRecorder $hop): PendingRequest
    {
        $cap = $this->settings->bodyReadCapBytes;

        return $this->http->withOptions([
            'allow_redirects' => false,
            'http_errors' => false,
            'timeout' => $remainingSeconds,
            'connect_timeout' => min($this->settings->connectTimeoutSeconds, $remainingSeconds),
            'headers' => ['User-Agent' => $this->settings->userAgent],
            'protocols' => self::ALLOWED_PROTOCOLS,
            'proxy' => self::NO_PROXY,
            'curl' => [CURLOPT_CONNECT_TO => [$this->connectTo($pinned)]],
            'on_stats' => static function (TransferStats $stats) use ($hop): void {
                $hop->record($stats->getHandlerStats(), $stats->getHandlerErrorData(), $stats->getResponse()?->getStatusCode());
            },
            'progress' => static fn (int $expectedBytes, int $receivedBytes): bool => $hop->exceedsBodyCap($receivedBytes, $cap),
        ]);
    }

    private function connectTo(PinnedTarget $pinned): string
    {
        $address = filter_var($pinned->address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false ? $pinned->address : "[{$pinned->address}]";

        return "::{$address}:{$pinned->target->port}";
    }

    private function failureKind(?int $errorCode): FailureKind
    {
        return match ($errorCode) {
            CURLE_OPERATION_TIMEDOUT => FailureKind::Timeout,
            CURLE_COULDNT_RESOLVE_HOST => FailureKind::Dns,
            CURLE_COULDNT_CONNECT, CURLE_GOT_NOTHING, CURLE_SEND_ERROR, CURLE_RECV_ERROR => FailureKind::Connect,
            CURLE_SSL_CONNECT_ERROR, CURLE_SSL_ENGINE_NOTFOUND, CURLE_SSL_ENGINE_SETFAILED, CURLE_SSL_CERTPROBLEM,
            CURLE_SSL_CIPHER, CURLE_SSL_CACERT, CURLE_SSL_CACERT_BADFILE, CURLE_SSL_PINNEDPUBKEYNOTMATCH => FailureKind::Tls,
            CURLE_ABORTED_BY_CALLBACK, CURLE_FILESIZE_EXCEEDED => FailureKind::BodyTooLarge,
            default => FailureKind::Unknown,
        };
    }

    private function timings(TransferRecorder $hop, PinnedTarget $pinned, int $startedAt): ProbeTimings
    {
        $stats = $hop->handlerStats;
        $lookup = $this->microseconds($stats, 'namelookup_time');
        $connect = $this->microseconds($stats, 'connect_time');
        $handshake = $this->microseconds($stats, 'appconnect_time');
        $pretransfer = $this->microseconds($stats, 'pretransfer_time');
        $firstByte = $this->microseconds($stats, 'starttransfer_time');

        return new ProbeTimings(
            dnsMs: $pinned->dnsMs,
            connectMs: $connect > 0 ? $this->phaseMs($lookup, $connect) : null,
            tlsMs: $handshake > 0 ? $this->phaseMs($connect, $handshake) : null,
            ttfbMs: $firstByte > 0 ? $this->phaseMs($pretransfer, $firstByte) : null,
            totalMs: $this->elapsedMs($startedAt),
        );
    }

    private function microseconds(array $stats, string $key): int
    {
        $exact = $stats["{$key}_us"] ?? null;
        if (is_int($exact)) {
            return $exact;
        }
        $seconds = $stats[$key] ?? null;

        return is_float($seconds) || is_int($seconds) ? (int) round($seconds * self::MICROSECONDS_PER_SECOND) : 0;
    }

    private function phaseMs(int $fromMicroseconds, int $toMicroseconds): int
    {
        return intdiv(max(0, $toMicroseconds - $fromMicroseconds), self::MICROSECONDS_PER_MILLISECOND);
    }

    private function elapsedMs(int $startedAt): int
    {
        return intdiv(hrtime(true) - $startedAt, self::NANOSECONDS_PER_MILLISECOND);
    }
}
