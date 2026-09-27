<?php

declare(strict_types=1);

namespace App\Providers;

use App\Incidents\Events\IncidentOpenedEvent;
use App\Incidents\Events\IncidentResolvedEvent;
use App\Monitoring\Events\CheckCompletedEvent;
use App\Monitoring\Events\MonitorChangedEvent;
use App\Realtime\Http\RealtimeMetaSection;
use App\Realtime\Listeners\BroadcastCheckCompleted;
use App\Realtime\Listeners\BroadcastIncidentOpened;
use App\Realtime\Listeners\BroadcastIncidentResolved;
use App\Realtime\Listeners\BroadcastMonitorChanged;
use App\Realtime\Settings\RealtimeSettings;
use App\Realtime\Settings\RealtimeSettingsParser;
use App\Shared\Meta\MetaSection;
use App\Shared\Settings\InvalidSettingException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class RealtimeServiceProvider extends ServiceProvider
{
    private const string SETTINGS_SOURCE = 'config/realtime.php';

    public function register(): void
    {
        $this->app->singleton(RealtimeSettings::class, function (): RealtimeSettings {
            $values = config('realtime');
            if (! is_array($values)) {
                throw new InvalidSettingException(self::SETTINGS_SOURCE, 'realtime', 'must return an array');
            }

            return (new RealtimeSettingsParser)->parse(self::SETTINGS_SOURCE, $values);
        });
        $this->app->tag(RealtimeMetaSection::class, MetaSection::TAG);
    }

    public function boot(): void
    {
        Event::listen(CheckCompletedEvent::class, BroadcastCheckCompleted::class);
        Event::listen(MonitorChangedEvent::class, BroadcastMonitorChanged::class);
        Event::listen(IncidentOpenedEvent::class, BroadcastIncidentOpened::class);
        Event::listen(IncidentResolvedEvent::class, BroadcastIncidentResolved::class);
    }
}
