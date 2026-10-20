<?php

declare(strict_types=1);

use App\Alerting\Enums\DeliveryKind;
use App\Alerting\Enums\DeliveryStatus;
use App\Alerting\Events\AlertDeliveredEvent;
use App\Alerting\Mail\AlertMail;
use App\Alerting\Models\AlertChannel;
use App\Alerting\Models\AlertDelivery;
use App\Monitoring\Events\CertificateExpiringEvent;
use App\Monitoring\Models\Monitor;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    $this->clock = freezeClock('2027-03-17 12:00:00');
    Mail::fake();
    Http::fake(['discord.com/*' => Http::response('', 204)]);
    $this->monitor = Monitor::factory()->create(['name' => 'Shop', 'url' => 'https://shop.example.com/']);
    $organizationId = $this->monitor->organization_id;
    $this->email = AlertChannel::factory()->create(['organization_id' => $organizationId, 'settings' => [AlertChannel::RECIPIENTS_SETTING => ['ops@example.com']]]);
    $this->discord = AlertChannel::factory()->discord()->create(['organization_id' => $organizationId]);
    $disabled = AlertChannel::factory()->discord('https://discord.com/api/webhooks/999/disabled-token')->disabled()->create(['organization_id' => $organizationId]);
    AlertChannel::factory()->create(['organization_id' => $organizationId]);
    foreach ([$this->email, $this->discord, $disabled] as $channel) {
        DB::table('alert_channel_monitor')->insert(['alert_channel_id' => $channel->id, 'monitor_id' => $this->monitor->id]);
    }
});

function certificateEvent(Monitor $monitor, string $expiresAt = '2027-03-31 12:00:00', int $days = 14): CertificateExpiringEvent
{
    return new CertificateExpiringEvent($monitor->id, $monitor->organization_id, CarbonImmutable::parse($expiresAt, 'UTC'), $days, CarbonImmutable::parse('2027-03-17 12:00:00', 'UTC'));
}

it('sends one certificate alert to every enabled channel of the monitor', function (): void {
    event(certificateEvent($this->monitor));

    $deliveries = AlertDelivery::query()->where('kind', DeliveryKind::CertificateExpiring)->get();
    expect($deliveries->pluck('alert_channel_id')->sort()->values()->all())->toBe(collect([$this->email->id, $this->discord->id])->sort()->values()->all())
        ->and($deliveries->pluck('status')->unique()->all())->toBe([DeliveryStatus::Sent])
        ->and($deliveries->pluck('incident_id')->unique()->all())->toBe([null])
        ->and($deliveries->pluck('certificate_days')->unique()->all())->toBe([14]);
    Mail::assertSent(AlertMail::class, fn (AlertMail $mail): bool => str_contains($mail->render(), 'Shop: TLS certificate expires in 14 days')
        && str_contains($mail->render(), '2027-03-31 12:00:00 UTC'));
    Http::assertSent(fn (Request $request): bool => data_get($request->data(), 'embeds.0.title') === 'Shop: TLS certificate expires in 14 days'
        && data_get($request->data(), 'embeds.0.url') === "http://localhost:5173/monitors/{$this->monitor->id}"
        && data_get($request->data(), 'embeds.0.color') === hexdec('f36e29'));
});

it('never sends twice for a replayed event', function (): void {
    event(certificateEvent($this->monitor));
    event(certificateEvent($this->monitor));

    expect(AlertDelivery::query()->where('kind', DeliveryKind::CertificateExpiring)->count())->toBe(2);
    Mail::assertSentCount(1);
    Http::assertSentCount(1);
});

it('sends again for the next threshold and for a renewed certificate', function (): void {
    event(certificateEvent($this->monitor));
    event(certificateEvent($this->monitor, days: 7));
    event(certificateEvent($this->monitor, expiresAt: '2027-06-29 12:00:00'));

    expect(AlertDelivery::query()->where('kind', DeliveryKind::CertificateExpiring)->count())->toBe(6);
    Mail::assertSentCount(3);
});

it('leaves the incident timelines alone', function (): void {
    Event::fake([AlertDeliveredEvent::class]);

    event(certificateEvent($this->monitor));

    Event::assertDispatched(AlertDeliveredEvent::class, fn (AlertDeliveredEvent $delivered): bool => $delivered->incidentId === null
        && $delivered->kind === DeliveryKind::CertificateExpiring);
    expect(DB::table('incident_events')->count())->toBe(0);
});

it('refuses a certificate delivery without its monitor, end and threshold', function (): void {
    expect(fn () => DB::table('alert_deliveries')->insert([
        'id' => strtolower((string) str()->ulid()),
        'alert_channel_id' => $this->email->id,
        'kind' => DeliveryKind::CertificateExpiring->value,
        'status' => DeliveryStatus::Pending->value,
        'attempts' => 0,
    ]))->toThrow(QueryException::class);
});
