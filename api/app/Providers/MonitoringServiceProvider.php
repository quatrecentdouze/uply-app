<?php

declare(strict_types=1);

namespace App\Providers;

use App\Monitoring\Console\DispatchChecksCommand;
use App\Monitoring\Console\MaintainCheckStorageCommand;
use App\Monitoring\Contracts\MonitorDirectory;
use App\Monitoring\Http\MonitoringMetaSection;
use App\Monitoring\Probe\DnsTargetGuard;
use App\Monitoring\Probe\HostResolver;
use App\Monitoring\Probe\HttpProbe;
use App\Monitoring\Probe\Probe;
use App\Monitoring\Probe\SystemHostResolver;
use App\Monitoring\Probe\TargetGuard;
use App\Monitoring\Settings\MonitoringSettings;
use App\Monitoring\Settings\MonitoringSettingsParser;
use App\Monitoring\Settings\ProbeSettings;
use App\Monitoring\Storage\DatabaseMonitorDirectory;
use App\Shared\Meta\MetaSection;
use App\Shared\Settings\InvalidSettingException;
use Illuminate\Support\ServiceProvider;

final class MonitoringServiceProvider extends ServiceProvider
{
    private const string SETTINGS_SOURCE = 'config/monitoring.php';

    public array $bindings = [
        MonitorDirectory::class => DatabaseMonitorDirectory::class,
        Probe::class => HttpProbe::class,
        TargetGuard::class => DnsTargetGuard::class,
        HostResolver::class => SystemHostResolver::class,
    ];

    public function register(): void
    {
        $this->app->singleton(MonitoringSettings::class, function (): MonitoringSettings {
            $values = config('monitoring');
            if (! is_array($values)) {
                throw new InvalidSettingException(self::SETTINGS_SOURCE, 'monitoring', 'must return an array');
            }

            return (new MonitoringSettingsParser)->parse(self::SETTINGS_SOURCE, $values);
        });
        $this->app->bind(ProbeSettings::class, fn (): ProbeSettings => $this->app->make(MonitoringSettings::class)->probe);
        $this->app->tag(MonitoringMetaSection::class, MetaSection::TAG);
    }

    public function boot(): void
    {
        $this->commands([
            DispatchChecksCommand::class,
            MaintainCheckStorageCommand::class,
        ]);
    }
}
