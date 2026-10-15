import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { landingSample } from '@/app/pages/landing/sample';
import { MonitorStatePill } from '@/features/monitors/components/MonitorStatePill';
import { ChecksTable } from '@/features/monitors/components/RecentChecksCard';
import { PublicMonitorCard } from '@/features/status-pages/components/PublicMonitorCard';
import { StatusBanner } from '@/features/status-pages/components/StatusBanner';
import { overallStateOf } from '@/features/status-pages/model/publicPage';
import type { TextKey } from '@/shared/i18n/textKey';
import { Activity, BellRing, Gauge, KeyRound, LayoutGrid, type LucideIcon, Siren, Timer, Users } from '@/shared/ui/icons';

const showcaseBars = 30;

const checkedMonitor = landingSample.monitors.find((monitor) => monitor.id === 'sample-checkout');

interface MiniFeature {
  icon: LucideIcon;
  title: TextKey<'landing'>;
  text: TextKey<'landing'>;
}

const miniFeatures: readonly MiniFeature[] = [
  { icon: BellRing, title: 'landing:features.alerts.title', text: 'landing:features.alerts.text' },
  { icon: Siren, title: 'landing:features.incidents.title', text: 'landing:features.incidents.text' },
  { icon: LayoutGrid, title: 'landing:features.dashboard.title', text: 'landing:features.dashboard.text' },
  { icon: Timer, title: 'landing:features.timings.title', text: 'landing:features.timings.text' },
  { icon: Users, title: 'landing:features.teams.title', text: 'landing:features.teams.text' },
  { icon: KeyRound, title: 'landing:features.security.title', text: 'landing:features.security.text' },
];

function FeatureCard({ icon: Icon, title, text, children }: { icon: LucideIcon; title: string; text: string; children: ReactNode }) {
  return (
    <article className="relative flex min-w-0 flex-col overflow-hidden rounded-card border-2 border-line bg-surface px-5 pt-6 sm:px-8 sm:pt-8">
      <span aria-hidden="true" className="mb-5 grid size-[41px] place-items-center rounded-[12px] border border-line-strong bg-linear-135 from-kbd to-surface-raised">
        <Icon className="size-5" strokeWidth={1.5} />
      </span>
      <h3 className="text-[22px] leading-[1.3] font-normal tracking-[-0.5px]">{title}</h3>
      <p className="mt-2.5 max-w-[420px] text-sm leading-[1.8] text-muted-foreground">{text}</p>
      <div inert className="mt-8 flex-1 rounded-t-[13px] border border-b-0 border-line-strong bg-panel px-3 pt-4 sm:px-5 sm:pt-5">
        {children}
      </div>
    </article>
  );
}

export function LandingFeatures() {
  const { t } = useTranslation(['landing']);
  return (
    <section id="features" aria-labelledby="features-title" className="scroll-mt-24 py-16 sm:py-20 lg:py-[114px]">
      <div className="mb-8 flex flex-col gap-4 sm:mb-11 md:flex-row md:items-end md:justify-between md:gap-10">
        <h2 id="features-title" className="text-[34px] leading-[1.15] font-normal tracking-[-1.2px] sm:text-[clamp(32px,3.4vw,46px)] sm:tracking-[-1.7px]">
          <span className="block">{t('landing:features.title_start')}</span>
          <span className="block">{t('landing:features.title_end')}</span>
        </h2>
        <p className="max-w-[360px] text-sm leading-[1.8] text-muted-foreground sm:text-[15px]">{t('landing:features.description')}</p>
      </div>
      <div className="grid gap-4 md:grid-cols-[1.25fr_1fr] md:gap-[22px]">
        <FeatureCard icon={Activity} title={t('landing:features.checks.title')} text={t('landing:features.checks.text')}>
          {checkedMonitor === undefined ? null : (
            <div className="mb-3 flex items-center justify-between gap-3 px-1">
              <span className="truncate text-base">{checkedMonitor.name}</span>
              <MonitorStatePill state={checkedMonitor.state} />
            </div>
          )}
          <ChecksTable checks={landingSample.checks} />
        </FeatureCard>
        <FeatureCard icon={Gauge} title={t('landing:features.status_pages.title')} text={t('landing:features.status_pages.text')}>
          <div className="grid gap-3 pb-3">
            <StatusBanner state={overallStateOf(landingSample.statusPage.overall_state)} />
            <ul className="grid gap-3">
              {landingSample.statusPage.monitors.map((monitor) => (
                <PublicMonitorCard key={monitor.id} monitor={{ ...monitor, bars: monitor.bars.slice(-showcaseBars) }} compactCount={showcaseBars} />
              ))}
            </ul>
          </div>
        </FeatureCard>
      </div>
      <ul className="mt-8 grid gap-x-8 gap-y-6 sm:mt-10 sm:grid-cols-2 lg:grid-cols-3">
        {miniFeatures.map(({ icon: Icon, title, text }) => (
          <li key={title} className="flex items-start gap-3">
            <Icon className="mt-0.5 size-[22px] shrink-0 text-muted-foreground" strokeWidth={1.5} aria-hidden="true" />
            <div>
              <h3 className="mb-1.5 text-sm font-normal">{t(title)}</h3>
              <p className="text-xs leading-[1.8] text-quiet">{t(text)}</p>
            </div>
          </li>
        ))}
      </ul>
    </section>
  );
}
