<?php

declare(strict_types=1);

namespace App\Monitoring\Probe;

use App\Monitoring\Enums\FailureKind;
use App\Monitoring\Rules\DiagnosticRules;
use App\Monitoring\Rules\ProbeHopRules;
use App\Monitoring\Settings\ProbeSettings;
use App\Monitoring\Values\PinnedTarget;
use App\Monitoring\Values\ProbeHop;
use App\Monitoring\Values\ProbeRequest;
use App\Monitoring\Values\ProbeResult;
use App\Monitoring\Values\ProbeTimings;
use App\Monitoring\Values\TargetRefusedException;
use App\Monitoring\Values\TargetUrl;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\TransferStats;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Psr\Http\Message\StreamInterface;
use Throwable;

final readonly class HttpProbe implements Probe
{
    private const int NANOSECONDS_PER_SECOND = 1_000_000_000;

    private const int NANOSECONDS_PER_MILLISECOND = 1_000_000;

    private const int MICROSECONDS_PER_MILLISECOND = 1_000;

    private const int MICROSECONDS_PER_SECOND = 1_000_000;

    private const array ALLOWED_PROTOCOLS = ['http', 'https'];

    private const array NO_PROXY = ['no' => ['*']];

    private const string BODY_BUFFER = 'php://temp';

    public function __construct(
        private Factory $http,
        private TargetGuard $guard,
        private ProbeSettings $settings,
    ) {}

    public function run(ProbeRequest $request): ProbeResult
    {
        $certificate = new CertificateRecorder;

        return $this->probe($request, $certificate)->withTlsExpiresAt($certificate->expiresAt);
    }

    private function probe(ProbeRequest $request, CertificateRecorder $certificate): ProbeResult
    {
        $startedAt = hrtime(true);
        $deadline = $startedAt + $request->timeoutSeconds * self::NANOSECONDS_PER_SECOND;
        $url = $request->url;
        $redirects = 0;
        $hop = ProbeHop::first($request->method, $request->request);
        $previousTarget = null;
        $redirectStatus = 0;
        $secrets = $request->request->secrets();

        while (true) {
            try {
                $pinned = $this->guard->pin($url);
            } catch (TargetRefusedException $refusal) {
                return $this->failed($secrets, $refusal->failureKind, $refusal->getMessage(), new ProbeTimings(dnsMs: $refusal->dnsMs, totalMs: $this->elapsedMs($startedAt)));
            }
            if ($previousTarget instanceof TargetUrl) {
                $hop = ProbeHopRules::follow($hop, $redirectStatus, $previousTarget, $pinned->target);
            }
            $remainingSeconds = ($deadline - hrtime(true)) / self::NANOSECONDS_PER_SECOND;
            if ($remainingSeconds <= 0) {
                return $this->failed($secrets, FailureKind::Timeout, "no time left before {$url}", new ProbeTimings(dnsMs: $pinned->dnsMs, totalMs: $this->elapsedMs($startedAt)));
            }

            $transfer = new TransferRecorder;
            try {
                $response = $this->pendingRequest($pinned, $remainingSeconds, $transfer, $hop, $request, $certificate->wants($pinned->target))->send($hop->method->verb(), $url);
                $certificate->record($pinned->target, $transfer->handlerStats);
            } catch (RequestException $exception) {
                $certificate->record($pinned->target, $transfer->handlerStats);
                $response = $exception->response;
            } catch (ConnectionException $exception) {
                $certificate->record($pinned->target, $transfer->handlerStats);
                $kind = $transfer->bodyCapExceeded ? FailureKind::BodyTooLarge : $this->failureKind($transfer->errorCode);

                return $this->failed($secrets, $kind, $exception->getMessage(), $this->timings($transfer, $pinned, $startedAt), $transfer->httpStatus);
            } catch (Throwable $exception) {
                $certificate->record($pinned->target, $transfer->handlerStats);
                $kind = $transfer->bodyCapExceeded ? FailureKind::BodyTooLarge : FailureKind::Unknown;

                return $this->failed($secrets, $kind, $exception->getMessage(), $this->timings($transfer, $pinned, $startedAt), $transfer->httpStatus);
            }

            $timings = $this->timings($transfer, $pinned, $startedAt);
            if ($transfer->bodyCapExceeded) {
                return $this->failed($secrets, FailureKind::BodyTooLarge, "body larger than {$this->settings->bodyReadCapBytes} bytes", $timings, $response->status());
            }
            $location = $response->header('Location');
            if (! $request->followRedirects || ! $response->redirect() || $location === '') {
                return ProbeResult::responded($response->status(), $timings, $request->readsBody ? $response->body() : null);
            }
            if ($redirects >= $this->settings->maxRedirects) {
                return $this->failed($secrets, FailureKind::TooManyRedirects, "more than {$this->settings->maxRedirects} redirects", $timings, $response->status());
            }
            try {
                $url = (string) UriResolver::resolve(Utils::uriFor($url), Utils::uriFor($location));
            } catch (Throwable $exception) {
                return $this->failed($secrets, FailureKind::Unknown, $exception->getMessage(), $timings, $response->status());
            }
            $previousTarget = $pinned->target;
            $redirectStatus = $response->status();
            $redirects++;
        }
    }

    private function failed(array $secrets, FailureKind $kind, string $message, ProbeTimings $timings, ?int $httpStatus = null): ProbeResult
    {
        return ProbeResult::failed($kind, DiagnosticRules::withoutSecrets($message, $secrets), $timings, $httpStatus);
    }

    private function pendingRequest(PinnedTarget $pinned, float $remainingSeconds, TransferRecorder $transfer, ProbeHop $hop, ProbeRequest $probeRequest, bool $readsCertificate): PendingRequest
    {
        $cap = $this->settings->bodyReadCapBytes;
        $request = $probeRequest->request;
        $options = [
            'allow_redirects' => false,
            'http_errors' => false,
            'timeout' => $remainingSeconds,
            'connect_timeout' => min($this->settings->connectTimeoutSeconds, $remainingSeconds),
            'headers' => ProbeHopRules::headers($hop, $request, $this->settings->userAgent),
            'protocols' => self::ALLOWED_PROTOCOLS,
            'proxy' => self::NO_PROXY,
            'curl' => [CURLOPT_CONNECT_TO => [$this->connectTo($pinned)], CURLOPT_CERTINFO => $readsCertificate],
            'on_stats' => static function (TransferStats $stats) use ($transfer): void {
                $transfer->record($stats->getHandlerStats(), $stats->getHandlerErrorData(), $stats->getResponse()?->getStatusCode());
            },
            'progress' => static fn (int $expectedBytes, int $receivedBytes): bool => $transfer->exceedsBodyCap($receivedBytes, $cap),
            'sink' => $this->sink($transfer, $probeRequest->readsBody),
        ];
        if ($hop->sendsBody && $request->body !== null) {
            $options['body'] = $request->body;
        }

        return $this->http->withOptions($options);
    }

    private function sink(TransferRecorder $transfer, bool $keepsBody): StreamInterface
    {
        $cap = $this->settings->bodyReadCapBytes;
        $buffer = Utils::streamFor(Utils::tryFopen(self::BODY_BUFFER, 'r+'));

        return FnStream::decorate($buffer, [
            'write' => static function (string $data) use ($buffer, $transfer, $cap, $keepsBody): int {
                if (! $keepsBody) {
                    return strlen($data);
                }
                if ($transfer->exceedsBodyCap(($buffer->getSize() ?? 0) + strlen($data), $cap)) {
                    return 0;
                }

                return $buffer->write($data);
            },
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

    private function timings(TransferRecorder $transfer, PinnedTarget $pinned, int $startedAt): ProbeTimings
    {
        $stats = $transfer->handlerStats;
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
