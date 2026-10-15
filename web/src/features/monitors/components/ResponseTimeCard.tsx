import { useTranslation } from 'react-i18next';
import { averageResponse, bucketLabelFormat, bucketTooltipFormat, responsePoints, windows, windowTabKeys } from '@/features/monitors/model/dashboard';
import type { Window } from '@/shared/api/queryKeys';
import type { Overview } from '@/shared/api/resources';
import { formatDate } from '@/shared/time/format';
import { BarTrend } from '@/shared/ui/chart-trends';
import { Skeleton } from '@/shared/ui/skeleton';
import { CardHeading, SurfaceCard } from '@/shared/ui/surface-card';
import type { Retry } from '@/shared/api/retry';
import { LoadError } from '@/shared/ui/load-error';

interface ResponseTimeCardProps {
  overview: Overview | undefined;
  retry?: Retry;
  window: Window;
  onWindowChange: (window: Window) => void;
}

export function ResponseTimeCard({ overview, window, onWindowChange, retry = null }: ResponseTimeCardProps) {
  const { t, i18n } = useTranslation(['monitors']);
  const series = overview?.series ?? [];
  const average = averageResponse(series);
  const context = { locale: i18n.language };
  const trend = responsePoints(series).map((point) => ({
    key: point.start,
    label: formatDate(point.start, context, bucketLabelFormat(window)),
    tooltip: formatDate(point.start, context, bucketTooltipFormat(window)),
    value: point.value,
    display: point.value === null ? '' : t('monitors:response.average', { value: point.value }),
  }));

  return (
    <SurfaceCard aria-labelledby="response-title" className="h-[417px] p-4 sm:h-[384px] sm:p-5">
      <CardHeading id="response-title" title={t('monitors:response.title')} info={t('monitors:response.info')} />
      <p className="sr-only">{t('monitors:response.average_label')}</p>
      {overview === undefined ? (
        retry === null ? <Skeleton className="mt-2.5 h-[45px] w-40" /> : null
      ) : (
        <p className="mt-2.5 text-[32px] leading-[45px] font-normal xl:text-[33px] 2xl:text-[38px]">
          {average === null ? t('monitors:response.no_data') : t('monitors:response.average', { value: average })}
        </p>
      )}
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
            className="h-10 min-w-[85px] rounded-[22px] md:h-[31px] px-[17px] text-base text-muted-foreground aria-selected:bg-bright aria-selected:text-ink"
          >
            {t(windowTabKeys[candidate])}
          </button>
        ))}
      </div>
      <div role="tabpanel" className="absolute right-3 bottom-4 left-3 h-[178px] sm:right-5 sm:left-5">
        {overview === undefined ? (
          retry === null ? <Skeleton className="h-full w-full" /> : <LoadError onRetry={retry} />
        ) : (
          <BarTrend points={trend} ariaLabel={t('monitors:response.chart')} formatTick={(value) => t('monitors:response.average', { value })} />
        )}
      </div>
    </SurfaceCard>
  );
}
