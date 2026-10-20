<?php

declare(strict_types=1);

namespace App\Alerting\Listeners;

use App\Alerting\Jobs\AlertQueue;
use App\Alerting\Messages\AlertMessageBuilder;
use App\Monitoring\Contracts\MonitorDirectory;
use App\Monitoring\Events\CertificateExpiringEvent;

final readonly class QueueCertificateAlerts
{
    public function __construct(
        private MonitorDirectory $monitors,
        private AlertMessageBuilder $messages,
        private AlertQueue $alerts,
    ) {}

    public function handle(CertificateExpiringEvent $event): void
    {
        $monitor = $this->monitors->find($event->monitorId);
        if ($monitor === null) {
            return;
        }

        $this->alerts->queueForCertificate(
            $event->monitorId,
            $event->expiresAt,
            $event->days,
            $this->messages->certificateExpiring($monitor, $event->expiresAt, $event->days, $event->detectedAt),
        );
    }
}
