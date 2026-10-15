import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { useMonitorChecks } from '@/features/monitors/api/monitors';
import { checkDetailText, checksOf, checkTimeFormat } from '@/features/monitors/model/monitorDetail';
import { dashboardSettings } from '@/features/monitors/model/settings';
import type { CheckResult } from '@/shared/api/resources';
import { formatDate } from '@/shared/time/format';
import { Button } from '@/shared/ui/button';
import { cellClass, DataTable, rowClass, secondaryCell } from '@/shared/ui/data-table';
import { retryOf } from '@/shared/api/retry';
import { LoadError } from '@/shared/ui/load-error';
import { Skeleton } from '@/shared/ui/skeleton';
import { CardHeading, Pill, SurfaceCard } from '@/shared/ui/surface-card';

function CheckRow({ check }: { check: CheckResult }) {
  const { t, i18n } = useTranslation(['monitors']);
  const detail = checkDetailText(check);
  return (
    <tr className={rowClass}>
      <td className={`${cellClass} pl-[18px] whitespace-nowrap text-muted-foreground`}>
        {check.checked_at === null ? '' : formatDate(check.checked_at, { locale: i18n.language }, checkTimeFormat)}
      </td>
      <td className={cellClass}>
        <Pill tone={check.outcome === 'up' ? 'positive' : 'alert'}>{t(check.outcome === 'up' ? 'monitors:states.up' : 'monitors:states.down')}</Pill>
      </td>
      <td className={`${cellClass} ${secondaryCell} truncate`}>{detail.status === null ? t(detail.key) : t(detail.key, { status: detail.status })}</td>
      <td className={`${cellClass} pr-4 text-right whitespace-nowrap`}>
        {check.response_ms === null ? t('monitors:list.no_data') : t('monitors:response.average', { value: check.response_ms })}
      </td>
    </tr>
  );
}

export function ChecksTable({ checks, className }: { checks: readonly CheckResult[]; className?: string }) {
  const { t } = useTranslation(['monitors']);
  return (
    <DataTable
      label={t('monitors:detail.checks_table')}
      className={cn('table-fixed', className)}
      head={[
        { label: t('monitors:detail.checked_at'), className: 'w-[40%] md:w-[32%]' },
        { label: t('monitors:detail.result'), className: 'w-[28%] md:w-[18%]' },
        { label: t('monitors:detail.detail'), className: 'w-[30%]', secondary: true },
        { label: t('monitors:list.response'), className: 'w-[32%] text-right md:w-[20%]' },
      ]}
    >
      {checks.map((check, index) => (
        <CheckRow key={`${check.checked_at ?? ''}-${String(index)}`} check={check} />
      ))}
    </DataTable>
  );
}

export function RecentChecksCard({ organizationId, monitorId }: { organizationId: string; monitorId: string }) {
  const { t } = useTranslation(['monitors']);
  const query = useMonitorChecks(organizationId, monitorId, dashboardSettings.checks_page_size);
  const checks = checksOf(query.data?.pages);
  const retry = retryOf(query);
  return (
    <SurfaceCard aria-labelledby="checks-title" className="px-2.5 pt-4 pb-3 sm:px-5">
      <div className="mb-[13px]">
        <CardHeading id="checks-title" title={t('monitors:detail.checks_title')} />
      </div>
      {query.data === undefined ? (
        retry === null ? <Skeleton className="h-[160px] w-full" /> : <LoadError onRetry={retry} />
      ) : checks.length === 0 ? (
        <p className="py-6 text-center text-muted-foreground">{t('monitors:detail.no_checks')}</p>
      ) : (
        <ChecksTable checks={checks} className="md:min-w-[560px]" />
      )}
      {query.hasNextPage ? (
        <div className="mt-3 flex justify-center">
          <Button
            type="button"
            variant="outline"
            disabled={query.isFetchingNextPage}
            aria-busy={query.isFetchingNextPage}
            className="h-10 rounded-[10px] md:h-9"
            onClick={() => {
              void query.fetchNextPage();
            }}
          >
            {t('monitors:detail.more_checks')}
          </Button>
        </div>
      ) : null}
    </SurfaceCard>
  );
}
