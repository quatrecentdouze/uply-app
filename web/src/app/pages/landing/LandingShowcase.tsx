import { useTranslation } from 'react-i18next';
import { landingSample } from '@/app/pages/landing/sample';
import { RecentIncidentsCard } from '@/features/incidents/components/RecentIncidentsCard';
import { MonitorPanel } from '@/features/monitors/components/MonitorPanel';
import { MonitorStatesCard } from '@/features/monitors/components/MonitorStatesCard';
import type { TextKey } from '@/shared/i18n/textKey';
import { Activity, BellRing, Gauge, Info, type LucideIcon, Timer } from '@/shared/ui/icons';

const showcaseMonitors = landingSample.monitors.filter((monitor) => !monitor.is_paused);

const capabilities: readonly { text: TextKey<'landing'>; icon: LucideIcon }[] = [
  { text: 'landing:capabilities.checks', icon: Activity },
  { text: 'landing:capabilities.uptime', icon: Timer },
  { text: 'landing:capabilities.alerts', icon: BellRing },
  { text: 'landing:capabilities.status_pages', icon: Gauge },
];

export function LandingShowcase() {
  const { t } = useTranslation(['landing']);
  return (
    <>
      <figure className="relative mt-10 motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-bottom-6 motion-safe:delay-300 motion-safe:duration-1000 motion-safe:fill-mode-both sm:mt-14">
        <div
          inert
          className="rounded-[18px] border border-line-strong bg-linear-130 from-surface-raised via-panel to-surface p-1 shadow-[0_30px_100px] shadow-overlay/40 sm:p-[7px]"
        >
          <div className="overflow-hidden rounded-[12px] border border-line-strong bg-page text-left">
            <div className="flex h-[54px] items-center justify-between gap-3 border-b-2 border-divider px-4 sm:h-[66px] sm:px-5">
              <span className="text-lg tracking-[-0.4px] sm:text-xl">{t('landing:showcase.title')}</span>
              <span className="rounded-full border border-line-strong bg-surface-raised px-2.5 py-1 text-xs text-quiet">{t('landing:showcase.badge')}</span>
            </div>
            <div className="grid gap-3 p-3 sm:gap-4 sm:p-4 xl:grid-cols-[minmax(0,1fr)_var(--spacing-panel-compact)]">
              <div className="grid min-w-0 content-start gap-3 sm:gap-4">
                <MonitorStatesCard overview={landingSample.overview} />
                <div className="max-sm:hidden">
                  <RecentIncidentsCard incidents={landingSample.incidents} now={landingSample.now} />
                </div>
              </div>
              <MonitorPanel monitors={showcaseMonitors} />
            </div>
          </div>
        </div>
        <figcaption className="mt-4 flex items-center justify-center gap-2 text-xs text-quiet">
          <Info className="size-3.5" aria-hidden="true" />
          {t('landing:showcase.caption')}
        </figcaption>
      </figure>
      <ul aria-label={t('landing:capabilities.label')} className="mt-8 grid grid-cols-2 gap-x-4 gap-y-4 border-b border-divider px-1 pb-8 sm:flex sm:flex-wrap sm:justify-between sm:px-[38px] sm:pb-10">
        {capabilities.map(({ text, icon: Icon }) => (
          <li key={text} className="flex items-center gap-2.5 text-left text-xs text-muted-foreground sm:text-sm">
            <Icon className="size-[18px] shrink-0 text-quiet" strokeWidth={1.5} aria-hidden="true" />
            {t(text)}
          </li>
        ))}
      </ul>
    </>
  );
}
