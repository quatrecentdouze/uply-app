<?php

declare(strict_types=1);

namespace App\Providers;

use App\Alerting\Console\SendRemindersCommand;
use App\Alerting\Console\SendTestAlertCommand;
use App\Alerting\Http\AlertingMetaSection;
use App\Alerting\Jobs\SendAlertJob;
use App\Alerting\Listeners\QueueCertificateAlerts;
use App\Alerting\Listeners\QueueDownAlerts;
use App\Alerting\Listeners\QueueUpAlerts;
use App\Alerting\Models\AlertChannel;
use App\Alerting\Policies\AlertChannelPolicy;
use App\Alerting\Settings\AlertingSettings;
use App\Alerting\Settings\AlertingSettingsParser;
use App\Incidents\Events\IncidentOpenedEvent;
use App\Incidents\Events\IncidentResolvedEvent;
use App\Monitoring\Events\CertificateExpiringEvent;
use App\Shared\Meta\MetaSection;
use App\Shared\Settings\InvalidSettingException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AlertingServiceProvider extends ServiceProvider
{
    private const string SETTINGS_SOURCE = 'config/alerting.php';

    public function register(): void
    {
        $this->app->singleton(AlertingSettings::class, function (): AlertingSettings {
            $values = config('alerting');
            if (! is_array($values)) {
                throw new InvalidSettingException(self::SETTINGS_SOURCE, 'alerting', 'must return an array');
            }

            return (new AlertingSettingsParser)->parse(self::SETTINGS_SOURCE, $values);
        });
        $this->app->tag(AlertingMetaSection::class, MetaSection::TAG);
    }

    public function boot(): void
    {
        Gate::policy(AlertChannel::class, AlertChannelPolicy::class);

        Event::listen(IncidentOpenedEvent::class, QueueDownAlerts::class);
        Event::listen(IncidentResolvedEvent::class, QueueUpAlerts::class);
        Event::listen(CertificateExpiringEvent::class, QueueCertificateAlerts::class);

        RateLimiter::for(SendAlertJob::WEBHOOK_LIMITER, fn (SendAlertJob $job): Limit => Limit::perSecond(
            $this->app->make(AlertingSettings::class)->delivery->messagesPerSecondPerWebhook,
        )->by($job->channelId));

        $this->commands([
            SendRemindersCommand::class,
            SendTestAlertCommand::class,
        ]);
    }
}
