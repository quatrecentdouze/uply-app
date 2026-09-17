<?php

declare(strict_types=1);

use App\Alerting\Enums\ChannelType;
use App\Alerting\Models\AlertChannel;
use App\Alerting\Settings\AlertingSettings;
use App\Monitoring\Enums\MonitorState;
use App\Monitoring\Models\Monitor;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Membership;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;

function useDemoWebhookFile(?string $content): void
{
    $path = null;
    if ($content !== null) {
        $path = tempnam(sys_get_temp_dir(), 'uply-demo-') ?: throw new RuntimeException('no temp file');
        file_put_contents($path, $content);
    }
    config(['alerting.demo_channels_file' => $path]);
    app()->forgetInstance(AlertingSettings::class);
}

beforeEach(fn () => app()->detectEnvironment(fn (): string => 'local'));

function linkedChannelTypes(): array
{
    $monitor = Monitor::query()->sole();
    $ids = DB::table('alert_channel_monitor')->where('monitor_id', $monitor->id)->pluck('alert_channel_id')->all();

    return AlertChannel::query()->whereIn('id', $ids)->orderBy('type')->get()->map(fn (AlertChannel $channel): string => $channel->type->value)->all();
}

it('seeds one demo organization with a due monitor and a mail channel, twice without duplicates', function (): void {
    useDemoWebhookFile(null);

    $this->artisan('db:seed', ['--class' => DemoSeeder::class])
        ->expectsOutputToContain('email_channel=ready')
        ->expectsOutputToContain('discord_channel=skipped reason=no_file')
        ->expectsOutputToContain('slack_channel=skipped reason=no_file')
        ->assertSuccessful();
    $this->seed(DemoSeeder::class);

    $monitor = Monitor::query()->sole();
    expect(Organization::query()->sole()->slug)->toBe(DemoSeeder::ORGANIZATION_SLUG)
        ->and($monitor->url)->toBe(DemoSeeder::TARGET_URL)
        ->and($monitor->interval_seconds)->toBe(30)
        ->and($monitor->state)->toBe(MonitorState::Pending)
        ->and(linkedChannelTypes())->toBe(['email'])
        ->and(AlertChannel::query()->sole()->recipients(1, 10)->addresses)->toBe([DemoSeeder::EMAIL_RECIPIENT]);
});

it('adds the discord and slack channels found in the local webhook file', function (): void {
    useDemoWebhookFile(json_encode([
        'discord' => 'https://discord.com/api/webhooks/42/seeded-token',
        'slack' => 'https://hooks.slack.com/services/T1/B1/seeded-token',
    ], JSON_THROW_ON_ERROR));

    $this->artisan('db:seed', ['--class' => DemoSeeder::class])
        ->expectsOutputToContain('discord_channel=ready')
        ->expectsOutputToContain('slack_channel=ready')
        ->doesntExpectOutputToContain('seeded-token')
        ->assertSuccessful();

    expect(linkedChannelTypes())->toBe(['discord', 'email', 'slack'])
        ->and(AlertChannel::query()->where('type', ChannelType::Discord)->sole()->is_enabled)->toBeTrue();
});

it('skips a missing key or a url outside the provider', function (): void {
    useDemoWebhookFile(json_encode(['discord' => 'https://example.com/hook'], JSON_THROW_ON_ERROR));

    $this->artisan('db:seed', ['--class' => DemoSeeder::class])
        ->expectsOutputToContain('discord_channel=skipped reason=invalid_url')
        ->expectsOutputToContain('slack_channel=skipped reason=no_key')
        ->assertSuccessful();

    expect(linkedChannelTypes())->toBe(['email']);
});

it('seeds the verified demo owner and viewer with the local password', function (): void {
    useDemoWebhookFile(null);

    $this->artisan('db:seed', ['--class' => DemoSeeder::class])->expectsOutputToContain('demo_users=ready')->assertSuccessful();
    $this->seed(DemoSeeder::class);

    $organization = Organization::query()->sole();
    $roles = Membership::query()->where('organization_id', $organization->id)->with('user')->get()
        ->mapWithKeys(fn (Membership $membership): array => [(string) $membership->user?->email => $membership->role])
        ->all();
    expect($roles)->toEqual([DemoSeeder::OWNER_EMAIL => Role::Owner, DemoSeeder::VIEWER_EMAIL => Role::Viewer])
        ->and(User::query()->whereNull('email_verified_at')->exists())->toBeFalse();

    app()->detectEnvironment(fn (): string => 'testing');
    $this->postJson('/api/v1/auth/login', ['email' => DemoSeeder::VIEWER_EMAIL, 'password' => DemoSeeder::LOCAL_PASSWORD])
        ->assertOk()
        ->assertExactJson(['two_factor' => false]);
});

it('refuses to seed outside the local environment', function (string $environment): void {
    app()->detectEnvironment(fn (): string => $environment);
    useDemoWebhookFile(null);

    expect(fn () => app(DemoSeeder::class)->setContainer(app())->__invoke())->toThrow(RuntimeException::class, 'local environment only');
    expect(User::query()->exists())->toBeFalse()
        ->and(Organization::query()->exists())->toBeFalse();
})->with(['testing', 'production']);
