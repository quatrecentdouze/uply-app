import { useTranslation } from 'react-i18next';
import { UptimeBars } from '@/features/status-pages/components/UptimeBars';
import { type PublicMonitorState, publicMonitorState } from '@/features/status-pages/model/publicPage';
import type { PublicMonitor } from '@/shared/api/resources';
import type { TextKey } from '@/shared/i18n/textKey';
import { Pill, type PillTone } from '@/shared/ui/surface-card';

const stateTones: Record<PublicMonitorState, PillTone> = {
  up: 'positive',
  down: 'alert',
  pending: 'pending',
  paused: 'paused',
};

const stateTextKeys: Record<PublicMonitorState, TextKey<'status-pages'>> = {
  up: 'status-pages:public.states.up',
  down: 'status-pages:public.states.down',
  pending: 'status-pages:public.states.pending',
  paused: 'status-pages:public.states.paused',
};

interface PublicMonitorCardProps {
  monitor: PublicMonitor;
  compactCount: number;
}

export function PublicMonitorCard({ monitor, compactCount }: PublicMonitorCardProps) {
  const { t } = useTranslation(['status-pages']);
  const state = publicMonitorState(monitor.state);
  const days = monitor.bars.length;
  const uptime = monitor.uptime_90d === null ? t('status-pages:public.uptime_none') : t('status-pages:public.uptime', { value: monitor.uptime_90d });
  const label =
    monitor.uptime_90d === null
      ? t('status-pages:public.strip_no_data', { name: monitor.display_name, days })
      : t('status-pages:public.strip', { name: monitor.display_name, days, value: monitor.uptime_90d });
  return (
    <li className="rounded-card border-2 border-line bg-surface p-4 sm:p-5">
      <div className="mb-4 flex items-center justify-between gap-3">
        <h3 className="min-w-0 truncate text-lg leading-6 font-normal">{monitor.display_name}</h3>
        <Pill tone={stateTones[state]}>{t(stateTextKeys[state])}</Pill>
      </div>
      <UptimeBars bars={monitor.bars} label={label} compactCount={compactCount} />
      <div className="mt-2.5 flex items-center justify-between gap-3 text-sm text-quiet">
        <span>
          <span className="sm:hidden">{t('status-pages:public.days_ago', { count: Math.min(days, compactCount) })}</span>
          <span className="max-sm:hidden">{t('status-pages:public.days_ago', { count: days })}</span>
        </span>
        <span className="text-foreground">{uptime}</span>
        <span>{t('status-pages:public.today')}</span>
      </div>
    </li>
  );
}
