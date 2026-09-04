<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;
use Symfony\Component\Process\Process;

final class ProbeTargetServer
{
    private const int STARTUP_ATTEMPTS = 200;

    private const int STARTUP_PAUSE_MICROSECONDS = 25_000;

    private static ?self $running = null;

    private function __construct(
        private readonly Process $process,
        public readonly int $port,
    ) {}

    public static function boot(): self
    {
        if (self::$running !== null) {
            return self::$running;
        }
        $port = self::freePort();
        $process = new Process([PHP_BINARY, '-S', "127.0.0.1:{$port}", __DIR__.'/probe-target.php']);
        $process->start();
        for ($attempt = 0; $attempt < self::STARTUP_ATTEMPTS; $attempt++) {
            if (str_contains($process->getErrorOutput().$process->getOutput(), 'started')) {
                return self::$running = new self($process, $port);
            }
            if (! $process->isRunning()) {
                break;
            }
            usleep(self::STARTUP_PAUSE_MICROSECONDS);
        }
        $process->stop(0);

        throw new RuntimeException('the probe target server did not start: '.$process->getErrorOutput());
    }

    public static function shutdown(): void
    {
        self::$running?->process->stop(0);
        self::$running = null;
    }

    public static function freePort(): int
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        if ($server === false) {
            throw new RuntimeException('no free local port');
        }
        $name = (string) stream_socket_get_name($server, false);
        fclose($server);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }

    public function url(string $path): string
    {
        return "http://127.0.0.1:{$this->port}{$path}";
    }
}
