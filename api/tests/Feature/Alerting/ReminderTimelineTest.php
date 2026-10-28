<?php

declare(strict_types=1);

use App\Alerting\Mail\AlertMail;
use App\Alerting\Models\AlertChannel;
use App\Alerting\Models\AlertDelivery;
use App\Incidents\Models\Incident;
use App\Monitoring\Enums\MonitorState;
use App\Monitoring\Models\Monitor;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Members;

it('mails the reminder and adds it to the incident timeline', function (): void {
    freezeClock('2027-03-10 12:00:00');
    Mail::fake();
    $monitor = Monitor::factory()->create(['name' => 'Billing API']);
    Monitor::query()->whereKey($monitor->id)->update(['state' => MonitorState::Down]);
    $incident = Incident::factory()->create(['monitor_id' => $monitor->id, 'started_at' => '2027-03-10 11:30:00+00']);
    $channel = AlertChannel::factory()->create(['organization_id' => $monitor->organization_id, 'reminder_interval_minutes' => 30, 'settings' => [AlertChannel::RECIPIENTS_SETTING => ['ops@example.com']]]);
    DB::table('alert_channel_monitor')->insert(['alert_channel_id' => $channel->id, 'monitor_id' => $monitor->id]);

    $this->artisan('alerts:remind')->expectsOutput('queued=1')->assertSuccessful();

    Mail::assertSent(AlertMail::class, fn (AlertMail $mail): bool => $mail->hasTo('ops@example.com') && $mail->alert->title === 'Billing API is still down');
    expect(AlertDelivery::query()->where('kind', 'reminder')->sole()->status->value)->toBe('sent');
    $this->actingAs(Members::seat(Organization::query()->findOrFail($monitor->organization_id), Role::Viewer))
        ->getJson("/api/v1/organizations/{$monitor->organization_id}/incidents/{$incident->id}")
        ->assertOk()
        ->assertJsonFragment(['kind' => 'alert_sent', 'alert_kind' => 'reminder', 'channel_id' => $channel->id, 'channel_type' => 'email']);
});
