<?php

declare(strict_types=1);

namespace App\Monitoring\Probe;

final class TransferRecorder
{
    public array $handlerStats = [];

    public ?int $errorCode = null;

    public ?int $httpStatus = null;

    public bool $bodyCapExceeded = false;

    public function record(array $handlerStats, mixed $handlerError, ?int $httpStatus): void
    {
        $this->handlerStats = $handlerStats;
        $this->errorCode = is_int($handlerError) && $handlerError !== 0 ? $handlerError : null;
        $this->httpStatus = $httpStatus;
    }

    public function exceedsBodyCap(int $receivedBytes, int $capBytes): bool
    {
        if ($receivedBytes > $capBytes) {
            $this->bodyCapExceeded = true;
        }

        return $this->bodyCapExceeded;
    }
}
