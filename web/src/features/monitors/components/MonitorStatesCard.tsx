import { useTranslation } from 'react-i18next';
import { type DisplayState, stateBreakdown, stateTextKeys } from '@/features/monitors/model/dashboard';
import type { Overview } from '@/shared/api/resources';
import { Skeleton } from '@/shared/ui/skeleton';
import { CardHeading, SurfaceCard } from '@/shared/ui/surface-card';

const stateColors: Record<DisplayState, string> = {
  up: 'bg-positive',
  down: 'bg-alert',
  paused: 'bg-paused',
  pending: 'bg-pending',
};

export function MonitorStatesCard({ overview }: { overview: Overview | undefined }) {
  const { t } = useTranslation(['monitors']);
  const breakdown = overview === undefined ? null : stateBreakdown(overview.counts);

  return (
    <SurfaceCard aria-labelledby="states-title" className="h-[295px] p-4 sm:h-[325px] sm:p-5">
      <CardHeading id="states-title" title={t('monitors:overview.title')} info={t('monitors:overview.info')} />
      <div className="mt-4 flex items-center gap-[18px]">
        {breakdown === null ? (
          <Skeleton className="h-[45px] w-40" />
        ) : (
          <p className="text-[32px] leading-[45px] font-normal whitespace-nowrap xl:text-[33px] 2xl:text-[37px]">{t('monitors:overview.total', { count: breakdown.total })}</p>
        )}
      </div>
      <div className="absolute right-4 bottom-5 left-4 sm:right-5 sm:left-5">
        <h3 className="mb-3.5 text-lg leading-[23px] font-light">{t('monitors:overview.breakdown')}</h3>
        {breakdown === null ? (
          <Skeleton className="h-[86px] w-full" />
        ) : (
          <>
            <ul className="mb-[13px] grid grid-flow-col gap-2" aria-label={t('monitors:overview.breakdown')}>
              {breakdown.segments.map((segment) => (
                <li key={segment.state} className="grid grid-cols-[13px_auto] gap-x-[9px] gap-y-[3px] text-base leading-[19px]">
                  <span aria-hidden="true" className={`mt-0.5 size-[13px] rounded-full ${stateColors[segment.state]}`} />
                  <span>{t(stateTextKeys[segment.state])}</span>
                  <span className="col-start-2 text-muted-foreground">{t('monitors:overview.count', { count: segment.count })}</span>
                </li>
              ))}
            </ul>
            <div role="img" aria-label={t('monitors:overview.bar')} className="flex h-12 w-full overflow-hidden bg-chip">
              {breakdown.segments.map((segment) =>
                segment.count === 0 ? null : (
                  <span key={segment.state} data-state={segment.state} className={stateColors[segment.state]} style={{ width: `${String(segment.percent)}%` }} />
                ),
              )}
            </div>
          </>
        )}
      </div>
    </SurfaceCard>
  );
}
