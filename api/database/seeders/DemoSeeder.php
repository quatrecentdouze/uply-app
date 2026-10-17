<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Alerting\Enums\ChannelType;
use App\Alerting\Models\AlertChannel;
use App\Alerting\Settings\AlertingSettings;
use App\Alerting\Values\WebhookUrl;
use App\Monitoring\Models\Monitor;
use App\Monitoring\Settings\MonitoringSettings;
use App\Organizations\Contracts\Role;
use App\Organizations\Models\Membership;
use App\Organizations\Models\Organization;
use App\Organizations\Models\User;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class DemoSeeder extends Seeder
{
    public const string ORGANIZATION_SLUG = 'demo';

    public const string TARGET_URL = 'http://127.0.0.1:8090/health';

    public const string EMAIL_RECIPIENT = 'demo@uply.test';

    public const string OWNER_EMAIL = 'demo@uply.test';

    public const string VIEWER_EMAIL = 'viewer@uply.test';

    public const string LOCAL_PASSWORD = 'password';

    private const string LOCAL_ENVIRONMENT = 'local';

    private const array CHANNEL_NAMES = [
        'email' => 'Demo mail',
        'discord' => 'Demo Discord',
        'slack' => 'Demo Slack',
    ];

    public function run(Application $app, MonitoringSettings $monitoring, AlertingSettings $alerting): void
    {
        if (! $app->environment(self::LOCAL_ENVIRONMENT)) {
            throw new RuntimeException('the demo seeder runs in the local environment only');
        }

        $organization = Organization::query()->firstOrCreate(['slug' => self::ORGANIZATION_SLUG], ['name' => 'Demo']);
        $this->member($organization, self::OWNER_EMAIL, 'Demo Owner', Role::Owner);
        $this->member($organization, self::VIEWER_EMAIL, 'Demo Viewer', Role::Viewer);
        $this->report('demo_users=ready');

        $monitor = Monitor::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'url' => self::TARGET_URL],
            [
                'name' => 'Local demo target',
                'method' => $monitoring->monitorDefaults->method,
                'interval_seconds' => $monitoring->intervals->shortestSeconds(),
                'timeout_seconds' => $monitoring->probe->defaultTimeoutSeconds,
                'accepted_status_codes' => $monitoring->monitorDefaults->acceptedStatusCodes->toTexts(),
                'follow_redirects' => true,
                'failure_threshold' => $monitoring->confirmation->thresholds->failuresToConfirmDown,
                'recovery_threshold' => $monitoring->confirmation->thresholds->successesToConfirmUp,
                'is_paused' => false,
                'next_check_at' => now(),
            ],
        );

        $this->link($monitor, $this->channel($organization, ChannelType::Email, [AlertChannel::RECIPIENTS_SETTING => [self::EMAIL_RECIPIENT]]));
        $this->report('email_channel=ready');

        $webhooks = $this->webhookFile($alerting->demoChannelsFile);
        foreach ([ChannelType::Discord, ChannelType::Slack] as $type) {
            $this->report($this->seedWebhookChannel($organization, $monitor, $type, $webhooks, $alerting));
        }
    }

    private function member(Organization $organization, string $email, string $name, Role $role): void
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill(['name' => $name, 'password' => self::LOCAL_PASSWORD, 'email_verified_at' => now()])->save();
        Membership::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'user_id' => $user->id],
            ['role' => $role],
        );
    }

    private function seedWebhookChannel(Organization $organization, Monitor $monitor, ChannelType $type, ?array $webhooks, AlertingSettings $alerting): string
    {
        $key = "{$type->value}_channel";
        if ($webhooks === null) {
            return "{$key}=skipped reason=no_file";
        }
        $url = $webhooks[$type->value] ?? null;
        if (! is_string($url)) {
            return "{$key}=skipped reason=no_key";
        }
        try {
            $webhook = WebhookUrl::parse($url, $alerting->webhooks->policyFor($type));
        } catch (InvalidArgumentException) {
            return "{$key}=skipped reason=invalid_url";
        }
        $this->link($monitor, $this->channel($organization, $type, [AlertChannel::WEBHOOK_URL_SETTING => $webhook->value]));

        return "{$key}=ready";
    }

    private function webhookFile(?string $path): ?array
    {
        if ($path === null || ! is_file($path)) {
            return null;
        }
        try {
            $content = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($content) ? $content : [];
    }

    private function channel(Organization $organization, ChannelType $type, array $settings): AlertChannel
    {
        return AlertChannel::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'type' => $type, 'name' => self::CHANNEL_NAMES[$type->value]],
            ['settings' => $settings, 'is_enabled' => true, 'disabled_reason' => null],
        );
    }

    private function link(Monitor $monitor, AlertChannel $channel): void
    {
        DB::table('alert_channel_monitor')->insertOrIgnore(['alert_channel_id' => $channel->id, 'monitor_id' => $monitor->id]);
    }

    private function report(string $line): void
    {
        $this->command?->line($line);
    }
}
