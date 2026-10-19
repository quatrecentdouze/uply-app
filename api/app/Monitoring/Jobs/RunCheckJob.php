<?php

declare(strict_types=1);

namespace App\Monitoring\Jobs;

use App\Monitoring\Rules\CheckOutcomeRules;
use App\Monitoring\Values\CompletedCheck;
use App\Shared\QueueName;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class RunCheckJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $monitorId,
        public readonly CarbonImmutable $scheduledAt,
    ) {
        $this->onQueue(QueueName::Checks->value);
    }

    public function handle(CheckContext $context): void
    {
        $monitor = $context->monitors->findCheckable($this->monitorId, $this->scheduledAt);
        if ($monitor === null) {
            return;
        }
        $checkedAt = $context->clock->now()->startOfSecond();
        $result = $context->probe->run($monitor->probeRequest());
        $verdict = CheckOutcomeRules::evaluate($monitor->acceptedStatusCodes(), $result, $monitor->keyword());

        $context->recorder->record(new CompletedCheck($this->monitorId, $this->scheduledAt, $checkedAt, $result->withoutBody(), $verdict));
    }
}
