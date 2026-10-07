import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { MonitorStatePill } from '@/features/monitors/components/MonitorStatePill';
import {
  bucketLabelFormat,
  bucketTooltipFormat,
  displayState,
  responsePoints,
  toneTextKeys,
  uptimeTone,
  windows,
  windowTabKeys,
} from '@/features/monitors/model/dashboard';
import { type TimingPhase, timingBreakdown, timingTextKeys } from '@/features/monitors/model/monitorDetail';
import { intervalText } from '@/features/monitors/model/monitorForm';
import { dashboardSettings } from '@/features/monitors/model/settings';
import type { Window } from '@/shared/api/queryKeys';
import type { Monitor, MonitorStats } from '@/shared/api/resources';
import { formatDate, formatRelative } from '@/shared/time/format';
import { Button } from '@/shared/ui/button';
import { BarTrend } from '@/shared/ui/chart-trends';
import { ExternalLink, Pencil, Trash2 } from '@/shared/ui/icons';
import { Label } from '@/shared/ui/label';
import { Skeleton } from '@/shared/ui/skeleton';
import { CardHeading, Pill, SurfaceCard } from '@/shared/ui/surface-card';
import { Switch } from '@/shared/ui/switch';

export interface MonitorHeaderActions {
  onTogglePause: (paused: boolean) => void;
  onEdit: () => void;
  onDelete: () => void;
  pausing: boolean;
}

export function MonitorHeaderCard({ monitor, now, actions }: { monitor: Monitor; now: Date; actions: MonitorHeaderActions | null }) {
  const { t, i18n } = useTranslation(['monitors']);
  const context = { locale: i18n.language };
  const interval = intervalText(monitor.interval_seconds);
  return (
    <SurfaceCard aria-labelledby="monitor-title" className="flex flex-wrap items-center gap-x-6 gap-y-4 p-4 sm:p-5">
      <div className="grid min-w-0 flex-1 gap-1.5">
        <div className="flex flex-wrap items-center gap-3">
          <h2 id="monitor-title" className="truncate text-[25px] leading-[32px] font-normal tracking-[-0.4px]">
            {monitor.name}
          </h2>
          <MonitorStatePill state={displayState(monitor)} />
        </div>
        <a href={monitor.url} target="_blank" rel="noreferrer noopener" className="flex min-w-0 items-center gap-1.5 text-muted-foreground hover:text-foreground">
          <span className="truncate">{monitor.url}</span>
          <ExternalLink className="size-4 shrink-0" aria-hidden="true" />
        </a>
        <p className="text-sm text-faint">
          {[
            t(interval.key, { count: interval.count }),
            monitor.last_checked_at === null ? t('monitors:list.never') : t('monitors:detail.last_check', { when: formatRelative(monitor.last_checked_at, now, context) }),
            monitor.state_changed_at === null ? null : t('monitors:detail.state_since', { when: formatDate(monitor.state_changed_at, context, { dateStyle: 'medium', timeStyle: 'short' }) }),
          ]
            .filter((part) => part !== null)
            .join(t('monitors:detail.separator'))}
        </p>
      </div>
      {actions === null ? null : (
        <div className="flex flex-wrap items-center gap-3">
          <div className="flex items-center gap-2.5 rounded-[13px] border-2 border-divider bg-surface-raised px-3.5 py-2">
            <Switch
              id="monitor-paused"
              checked={monitor.is_paused}
              disabled={actions.pausing}
              onCheckedChange={(checked) => {
                actions.onTogglePause(checked);
              }}
            />
            <Label htmlFor="monitor-paused" className="text-base font-normal">
              {t('monitors:detail.paused')}
            </Label>
          </div>
          <Button type="button" variant="outline" className="h-10 rounded-[11px]" onClick={actions.onEdit}>
            <Pencil aria-hidden="true" />
            {t('monitors:list.edit')}
          </Button>
          <Button type="button" variant="destructive" className="h-10 rounded-[11px]" onClick={actions.onDelete}>
            <Trash2 aria-hidden="true" />
            {t('monitors:list.delete')}
          </Button>
        </div>
      )}
    </SurfaceCard>
  );
}

export function UptimeStatCard({ window, stats }: { window: Window; stats: MonitorStats | undefined }) {
  const { t } = useTranslation(['monitors']);
  const percent = stats?.uptime_percent ?? null;
  const tone = uptimeTone(percent, dashboardSettings.uptime_thresholds);
  const id = `uptime-${window}`;
  return (
    <SurfaceCard aria-labelledby={id} className="p-4 sm:p-5">
      <CardHeading id={id} title={t('monitors:detail.uptime', { window: t(windowTabKeys[window]) })} />
      {stats === undefined ? (
        <Skeleton className="mt-2 h-[45px] w-32" />
      ) : (
        <div className="mt-2 flex flex-wrap items-center gap-3">
          <p className="text-[32px] leading-[45px] font-normal">{percent === null ? t('monitors:uptime.no_data') : t('monitors:uptime.percent', { value: percent })}</p>
          <Pill tone={tone}>{t(toneTextKeys[tone])}</Pill>
        </div>
      )}
      <p className="mt-1 text-sm text-muted-foreground">{t('monitors:detail.checks', { count: stats?.checks_total ?? 0 })}</p>
    </SurfaceCard>
  );
}

interface MonitorResponseCardProps {
  stats: MonitorStats | undefined;
  window: Window;
  onWindowChange: (window: Window) => void;
}

export function MonitorResponseCard({ stats, window, onWindowChange }: MonitorResponseCardProps) {
  const { t, i18n } = useTranslation(['monitors']);
  const context = { locale: i18n.language };
  const trend = responsePoints(stats?.series ?? []).map((point) => ({
    key: point.start,
    label: formatDate(point.start, context, bucketLabelFormat(window)),
    tooltip: formatDate(point.start, context, bucketTooltipFormat(window)),
    value: point.value,
    display: point.value === null ? '' : t('monitors:response.average', { value: point.value }),
  }));
  const figures = [
    { key: 'avg', label: t('monitors:detail.response.avg'), value: stats?.response_ms.avg ?? null },
    { key: 'min', label: t('monitors:detail.response.min'), value: stats?.response_ms.min ?? null },
    { key: 'max', label: t('monitors:detail.response.max'), value: stats?.response_ms.max ?? null },
  ];
  return (
    <SurfaceCard aria-labelledby="monitor-response-title" className="p-4 sm:p-5">
      <CardHeading id="monitor-response-title" title={t('monitors:response.title')} info={t('monitors:response.info')} />
      <dl className="mt-2.5 flex flex-wrap gap-x-8 gap-y-2">
        {figures.map((figure) => (
          <div key={figure.key} className="grid gap-0.5">
            <dt className="text-sm text-muted-foreground">{figure.label}</dt>
            <dd className="text-[26px] leading-[34px]">{figure.value === null ? t('monitors:response.no_data') : t('monitors:response.average', { value: figure.value })}</dd>
          </div>
        ))}
      </dl>
      <div role="tablist" aria-label={t('monitors:response.tabs')} className="mt-[17px] flex items-center gap-[5px]">
        {windows.map((candidate) => (
          <button
            key={candidate}
            type="button"
            role="tab"
            aria-selected={candidate === window}
            onClick={() => {
              onWindowChange(candidate);
            }}
            className="h-[31px] min-w-[85px] rounded-[22px] px-[17px] text-base text-muted-foreground aria-selected:bg-bright aria-selected:text-ink"
          >
            {t(windowTabKeys[candidate])}
          </button>
        ))}
      </div>
      <div role="tabpanel" className="mt-4 h-[200px]">
        {stats === undefined ? (
          <Skeleton className="h-full w-full" />
        ) : (
          <BarTrend points={trend} ariaLabel={t('monitors:response.chart')} formatTick={(value) => t('monitors:response.average', { value })} />
        )}
      </div>
    </SurfaceCard>
  );
}

const phaseColors: Record<TimingPhase, string> = {
  dns: 'bg-glow-blue',
  connect: 'bg-glow-gold',
  tls: 'bg-glow-orange',
  ttfb: 'bg-primary',
};

export function TimingBreakdownCard({ stats }: { stats: MonitorStats | undefined }) {
  const { t } = useTranslation(['monitors']);
  const breakdown = stats === undefined ? null : timingBreakdown(stats.timings);
  return (
    <SurfaceCard aria-labelledby="timings-title" className="p-4 sm:p-5">
      <CardHeading id="timings-title" title={t('monitors:detail.timings.title')} info={t('monitors:detail.timings.info')} />
      {breakdown === null ? (
        <Skeleton className="mt-4 h-[90px] w-full" />
      ) : breakdown.total === 0 ? (
        <p className="mt-4 text-muted-foreground">{t('monitors:response.no_data')}</p>
      ) : (
        <>
          <div role="img" aria-label={t('monitors:detail.timings.bar')} className="mt-4 flex h-[18px] w-full overflow-hidden rounded-full bg-chip">
            {breakdown.segments.map((segment) => (
              <span key={segment.phase} className={cn('h-full', phaseColors[segment.phase])} style={{ width: `${String(segment.percent)}%` }} />
            ))}
          </div>
          <dl className="mt-4 grid grid-cols-2 gap-3">
            {breakdown.segments.map((segment) => (
              <div key={segment.phase} className="grid gap-0.5">
                <dt className="flex items-center gap-2 text-sm text-muted-foreground">
                  <span aria-hidden="true" className={cn('size-2.5 rounded-full', phaseColors[segment.phase])} />
                  {t(timingTextKeys[segment.phase])}
                </dt>
                <dd className="text-lg">{t('monitors:response.average', { value: segment.milliseconds })}</dd>
              </div>
            ))}
          </dl>
        </>
      )}
    </SurfaceCard>
  );
}
