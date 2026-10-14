import { useTranslation } from 'react-i18next';
import { bucketLabelFormat, bucketTooltipFormat, toneTextKeys, uptimeFloor, uptimePoints, windows, windowTextKeys } from '@/features/monitors/model/dashboard';
import type { Window } from '@/shared/api/queryKeys';
import type { Overview } from '@/shared/api/resources';
import { formatDate } from '@/shared/time/format';
import { AreaTrend } from '@/shared/ui/chart-trends';
import { DropdownMenu, DropdownMenuContent, DropdownMenuRadioGroup, DropdownMenuRadioItem, DropdownMenuTrigger } from '@/shared/ui/dropdown-menu';
import { ChevronDown } from '@/shared/ui/icons';
import { Skeleton } from '@/shared/ui/skeleton';
import { CardHeading, Pill, SurfaceCard } from '@/shared/ui/surface-card';
import { uptimeSettings, uptimeTone } from '@/shared/uptime/uptime';
import type { Retry } from '@/shared/api/retry';
import { LoadError } from '@/shared/ui/load-error';

interface UptimeCardProps {
  overview: Overview | undefined;
  retry?: Retry;
  window: Window;
  onWindowChange: (window: Window) => void;
}

export function WindowMenu({ window, onWindowChange }: Pick<UptimeCardProps, 'window' | 'onWindowChange'>) {
  const { t } = useTranslation(['monitors']);
  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        aria-label={t('monitors:period')}
        className="flex min-h-10 items-center gap-2 rounded-[9px] border-2 border-line-strong bg-field px-3 text-base whitespace-nowrap outline-none focus-visible:ring-2 focus-visible:ring-ring 2xl:text-[17px]"
      >
        <span>{t(windowTextKeys[window])}</span>
        <ChevronDown className="size-4" aria-hidden="true" />
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <DropdownMenuRadioGroup
          value={window}
          onValueChange={(value) => {
            const next = windows.find((candidate) => candidate === value);
            if (next !== undefined) {
              onWindowChange(next);
            }
          }}
        >
          {windows.map((candidate) => (
            <DropdownMenuRadioItem key={candidate} value={candidate}>
              {t(windowTextKeys[candidate])}
            </DropdownMenuRadioItem>
          ))}
        </DropdownMenuRadioGroup>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

export function UptimeCard({ overview, window, onWindowChange, retry = null }: UptimeCardProps) {
  const { t, i18n } = useTranslation(['monitors']);
  const percent = overview?.uptime_percent ?? null;
  const tone = uptimeTone(percent, uptimeSettings.thresholds);
  const points = uptimePoints(overview?.series ?? []);
  const context = { locale: i18n.language };
  const trend = points.map((point) => ({
    key: point.start,
    label: formatDate(point.start, context, bucketLabelFormat(window)),
    tooltip: formatDate(point.start, context, bucketTooltipFormat(window)),
    value: point.value,
    display: point.value === null ? '' : t('monitors:uptime.percent', { value: point.value }),
  }));

  return (
    <SurfaceCard aria-labelledby="uptime-title" className="h-[310px] p-4 sm:h-[325px] sm:p-5 xl:p-5">
      <CardHeading id="uptime-title" title={t('monitors:uptime.title')} info={t('monitors:uptime.info')} action={<WindowMenu window={window} onWindowChange={onWindowChange} />} />
      <div className="mt-2.5 flex items-center gap-[18px]">
        {overview === undefined ? (
          retry === null ? <Skeleton className="h-[45px] w-40" /> : null
        ) : (
          <p className="text-[32px] leading-[45px] font-normal whitespace-nowrap xl:text-[33px] 2xl:text-[37px]">
            {percent === null ? t('monitors:uptime.no_data') : t('monitors:uptime.percent', { value: percent })}
          </p>
        )}
        <Pill tone={tone}>{t(toneTextKeys[tone])}</Pill>
      </div>
      <div className="absolute right-[19px] bottom-[15px] left-5 h-[167px]">
        {overview === undefined ? retry === null ? <Skeleton className="h-full w-full" /> : <LoadError onRetry={retry} /> : <AreaTrend points={trend} ariaLabel={t('monitors:uptime.chart')} floor={uptimeFloor(points)} />}
      </div>
    </SurfaceCard>
  );
}
