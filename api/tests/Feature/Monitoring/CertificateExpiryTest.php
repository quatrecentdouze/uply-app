<?php

declare(strict_types=1);

use App\Monitoring\Events\CertificateExpiringEvent;
use App\Monitoring\Models\Monitor;
use App\Monitoring\Probe\Probe;
use App\Monitoring\Storage\CheckStorageMaintenance;
use App\Monitoring\Values\ProbeRequest;
use App\Monitoring\Values\ProbeResult;
use App\Monitoring\Values\ProbeTimings;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Tests\Support\Members;

beforeEach(function (): void {
    $this->clock = freezeClock('2027-03-10 12:00:00');
    app(CheckStorageMaintenance::class)->run($this->clock->now());
    $this->probe = new class implements Probe
    {
        public ?CarbonImmutable $certificateEnd = null;

        public function run(ProbeRequest $request): ProbeResult
        {
            return ProbeResult::responded(200, new ProbeTimings(totalMs: 40))->withTlsExpiresAt($this->certificateEnd);
        }
    };
    app()->instance(Probe::class, $this->probe);
    Event::fake([CertificateExpiringEvent::class]);
    $this->monitor = Monitor::factory()->create(['url' => 'https://shop.example.com/', 'next_check_at' => $this->clock->now()]);
});

function checkCertificateAt(object $test, string $moment, ?string $certificateEnd): Monitor
{
    $test->probe->certificateEnd = $certificateEnd === null ? null : CarbonImmutable::parse($certificateEnd, 'UTC');
    $test->clock->moveTo(CarbonImmutable::parse($moment, 'UTC'));
    app(CheckStorageMaintenance::class)->run($test->clock->now());
    Monitor::query()->whereKey($test->monitor->id)->update(['next_check_at' => $test->clock->now()]);
    $test->artisan('checks:dispatch')->assertSuccessful();

    return $test->monitor->fresh() ?? throw new LogicException('the monitor is gone');
}

it('stores the end of the certificate and alerts once at each threshold', function (): void {
    $far = checkCertificateAt($this, '2027-03-01 12:00:00', '2027-03-31 12:00:00');
    $fourteen = checkCertificateAt($this, '2027-03-17 12:00:00', '2027-03-31 12:00:00');
    $again = checkCertificateAt($this, '2027-03-17 12:01:00', '2027-03-31 12:00:00');
    $seven = checkCertificateAt($this, '2027-03-24 12:00:00', '2027-03-31 12:00:00');

    expect($far->tls_expires_at?->toIso8601ZuluString())->toBe('2027-03-31T12:00:00Z')
        ->and($far->tls_alerted_days)->toBeNull()
        ->and($fourteen->tls_alerted_days)->toBe(14)
        ->and($again->tls_alerted_days)->toBe(14)
        ->and($seven->tls_alerted_days)->toBe(7);
    Event::assertDispatchedTimes(CertificateExpiringEvent::class, 2);
    Event::assertDispatched(CertificateExpiringEvent::class, fn (CertificateExpiringEvent $event): bool => $event->monitorId === $this->monitor->id
        && $event->organizationId === $this->monitor->organization_id
        && $event->days === 14
        && $event->expiresAt->toIso8601ZuluString() === '2027-03-31T12:00:00Z'
        && $event->detectedAt->toIso8601ZuluString() === '2027-03-17T12:00:00Z');
    Event::assertDispatched(CertificateExpiringEvent::class, fn (CertificateExpiringEvent $event): bool => $event->days === 7);
});

it('keeps the last end when a check gets no certificate', function (): void {
    checkCertificateAt($this, '2027-03-17 12:00:00', '2027-03-31 12:00:00');
    $without = checkCertificateAt($this, '2027-03-17 12:05:00', null);

    expect($without->tls_expires_at?->toIso8601ZuluString())->toBe('2027-03-31T12:00:00Z')
        ->and($without->tls_alerted_days)->toBe(14);
});

it('starts again from nothing for a renewed certificate', function (): void {
    checkCertificateAt($this, '2027-03-30 12:00:00', '2027-03-31 12:00:00');
    $renewed = checkCertificateAt($this, '2027-03-30 13:00:00', '2027-06-28 12:00:00');
    $later = checkCertificateAt($this, '2027-06-14 12:00:00', '2027-06-28 12:00:00');

    expect($renewed->tls_expires_at?->toIso8601ZuluString())->toBe('2027-06-28T12:00:00Z')
        ->and($renewed->tls_alerted_days)->toBeNull()
        ->and($later->tls_alerted_days)->toBe(14);
    Event::assertDispatchedTimes(CertificateExpiringEvent::class, 2);
});

it('sends nothing for a certificate already past its end', function (): void {
    checkCertificateAt($this, '2027-04-01 12:00:00', '2027-03-31 12:00:00');

    Event::assertNotDispatched(CertificateExpiringEvent::class);
});

it('forgets the certificate when the url changes, not on a rename', function (): void {
    checkCertificateAt($this, '2027-03-17 12:00:00', '2027-03-31 12:00:00');
    $route = "/api/v1/organizations/{$this->monitor->organization_id}/monitors/{$this->monitor->id}";
    $this->actingAs(Members::seat(Organization::query()->findOrFail($this->monitor->organization_id), Role::Member));

    $this->patchJson($route, ['name' => 'Shop'])->assertOk();
    expect($this->monitor->fresh()?->tls_alerted_days)->toBe(14);

    $this->patchJson($route, ['url' => 'https://store.example.com/'])->assertOk();
    $moved = $this->monitor->fresh();
    expect($moved?->tls_expires_at)->toBeNull()
        ->and($moved?->tls_alerted_days)->toBeNull();
});
