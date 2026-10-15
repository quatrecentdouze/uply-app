import { Link } from '@tanstack/react-router';
import { useTranslation } from 'react-i18next';
import { type DisplayState, displayState, panelMonitors, stateTextKeys } from '@/features/monitors/model/dashboard';
import { dashboardSettings } from '@/features/monitors/model/settings';
import type { Monitor } from '@/shared/api/resources';
import { Activity, ChevronRight, Plus } from '@/shared/ui/icons';
import { Skeleton } from '@/shared/ui/skeleton';
import { CardHeading, Pill, type PillTone, SurfaceCard } from '@/shared/ui/surface-card';
import type { Retry } from '@/shared/api/retry';
import { LoadError } from '@/shared/ui/load-error';

const stateTones: Record<DisplayState, PillTone> = {
  up: 'positive',
  down: 'alert',
  pending: 'pending',
  paused: 'paused',
};

const stateIcons: Record<DisplayState, string> = {
  up: 'bg-positive-soft text-positive',
  down: 'bg-alert-soft text-alert',
  pending: 'bg-pending-soft text-pending',
  paused: 'bg-paused-soft text-paused',
};

function MonitorRow({ monitor }: { monitor: Monitor }) {
  const { t } = useTranslation(['monitors']);
  const state = displayState(monitor);
  return (
    <li className="overflow-hidden rounded-[15px] border-2 border-divider bg-surface-raised">
      <Link
        to="/monitors/$monitorId"
        params={{ monitorId: monitor.id }}
        aria-label={t('monitors:panel.open', { name: monitor.name })}
        className="flex h-[78px] w-full items-center gap-[13px] px-[15px] text-left hover:bg-secondary"
      >
        <span aria-hidden="true" className={`grid size-[46px] shrink-0 place-items-center rounded-[14px] ${stateIcons[state]}`}>
          <Activity className="size-6" />
        </span>
        <span className="flex min-w-0 flex-col gap-1.5">
          <span className="truncate text-[17px] leading-[22px]">{monitor.name}</span>
          <span className="truncate text-sm leading-[19px] text-muted-foreground">{monitor.url}</span>
        </span>
        <ChevronRight className="ml-auto size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
      </Link>
      <div className="flex h-10 items-center justify-between bg-secondary px-[15px]">
        <span className="text-lg">
          {monitor.last_response_ms === null ? t('monitors:panel.no_response') : t('monitors:panel.last_response', { value: monitor.last_response_ms })}
        </span>
        <Pill tone={stateTones[state]}>{t(stateTextKeys[state])}</Pill>
      </div>
    </li>
  );
}

export function MonitorPanel({ monitors, retry = null }: { monitors: readonly Monitor[] | undefined; retry?: Retry }) {
  const { t } = useTranslation(['monitors']);
  const shown = monitors === undefined ? undefined : panelMonitors(monitors, dashboardSettings.panel_monitors);
  return (
    <SurfaceCard aria-labelledby="panel-title" className="bg-panel p-4 sm:p-5 lg:min-h-[615px]">
      <CardHeading
        id="panel-title"
        title={t('monitors:panel.title')}
        action={
          <Link to="/monitors" aria-label={t('monitors:panel.add')} className="grid size-10 place-items-center md:size-[35px] rounded-[10px] border-2 border-chip-line bg-secondary">
            <Plus className="size-5" strokeWidth={1.3} aria-hidden="true" />
          </Link>
        }
      />
      <div className="mt-[17px]">
        {shown === undefined && retry !== null ? (
          <LoadError onRetry={retry} />
        ) : shown === undefined ? (
          <div className="flex flex-col gap-[17px]">
            <Skeleton className="h-[118px] w-full" />
            <Skeleton className="h-[118px] w-full" />
          </div>
        ) : shown.length === 0 ? (
          <p className="py-8 text-center text-muted-foreground">{t('monitors:panel.empty')}</p>
        ) : (
          <ul className="grid gap-3.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-1 xl:gap-[17px]">
            {shown.map((monitor) => (
              <MonitorRow key={monitor.id} monitor={monitor} />
            ))}
          </ul>
        )}
      </div>
      <Link
        to="/monitors"
        className="mt-[17px] grid h-[41px] w-full place-items-center rounded-[9px] border border-transparent [background:linear-gradient(var(--color-surface-raised),var(--color-surface-raised))_padding-box,linear-gradient(120deg,var(--color-glow-start),var(--color-glow-gold)_65%,var(--color-glow-blue))_border-box] text-sm"
      >
        {t('monitors:panel.view_all')}
      </Link>
    </SurfaceCard>
  );
}
