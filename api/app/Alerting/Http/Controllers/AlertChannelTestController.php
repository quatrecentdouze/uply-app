<?php

declare(strict_types=1);

namespace App\Alerting\Http\Controllers;

use App\Alerting\Actions\SendTestAlert;
use App\Alerting\Enums\AlertingError;
use App\Alerting\Enums\TestAlertStatus;
use App\Alerting\Models\AlertChannel;
use App\Alerting\Storage\AlertChannelQuery;
use App\Shared\Clock;
use App\Shared\Errors\DomainError;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final readonly class AlertChannelTestController
{
    public function __construct(
        private AlertChannelQuery $channels,
        private SendTestAlert $sendTestAlert,
        private Clock $clock,
    ) {}

    public function store(string $organization, string $channel): JsonResponse
    {
        $outcome = ($this->sendTestAlert)($this->channels->get($organization, $channel)->id, $this->clock);

        if ($outcome->status === TestAlertStatus::Limited) {
            $seconds = $outcome->retryInSeconds ?? 1;

            throw new DomainError(AlertingError::TestLimitReached, ['seconds' => $seconds], ['Retry-After' => (string) $seconds]);
        }
        if ($outcome->status === TestAlertStatus::ChannelNotFound) {
            throw (new ModelNotFoundException)->setModel(AlertChannel::class, [$channel]);
        }

        return new JsonResponse(['data' => ['delivery_id' => $outcome->deliveryId]], Response::HTTP_ACCEPTED);
    }
}
