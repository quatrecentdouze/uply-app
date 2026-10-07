import { useTranslation } from 'react-i18next';
import { useMonitorChecks } from '@/features/monitors/api/monitors';
import { checkDetailText, checksOf, checkTimeFormat } from '@/features/monitors/model/monitorDetail';
import { dashboardSettings } from '@/features/monitors/model/settings';
import type { CheckResult } from '@/shared/api/resources';
import { formatDate } from '@/shared/time/format';
import { Button } from '@/shared/ui/button';
import { cellClass, DataTable, rowClass } from '@/shared/ui/data-table';
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
      <td className={`${cellClass} truncate`}>{detail.status === null ? t(detail.key) : t(detail.key, { status: detail.status })}</td>
      <td className={`${cellClass} pr-4 text-right whitespace-nowrap`}>
        {check.response_ms === null ? t('monitors:list.no_data') : t('monitors:response.average', { value: check.response_ms })}
      </td>
    </tr>
  );
}

export function RecentChecksCard({ organizationId, monitorId }: { organizationId: string; monitorId: string }) {
  const { t } = useTranslation(['monitors']);
  const query = useMonitorChecks(organizationId, monitorId, dashboardSettings.checks_page_size);
  const checks = checksOf(query.data?.pages);
  return (
    <SurfaceCard aria-labelledby="checks-title" className="px-2.5 pt-4 pb-3 sm:px-5">
      <div className="mb-[13px]">
        <CardHeading id="checks-title" title={t('monitors:detail.checks_title')} />
      </div>
      {query.data === undefined ? (
        <Skeleton className="h-[160px] w-full" />
      ) : checks.length === 0 ? (
        <p className="py-6 text-center text-muted-foreground">{t('monitors:detail.no_checks')}</p>
      ) : (
        <DataTable
          label={t('monitors:detail.checks_table')}
          className="min-w-[560px] table-fixed"
          head={[
            { label: t('monitors:detail.checked_at'), className: 'w-[32%]' },
            { label: t('monitors:detail.result'), className: 'w-[18%]' },
            { label: t('monitors:detail.detail'), className: 'w-[30%]' },
            { label: t('monitors:list.response'), className: 'w-[20%] text-right' },
          ]}
        >
          {checks.map((check, index) => (
            <CheckRow key={`${check.checked_at ?? ''}-${String(index)}`} check={check} />
          ))}
        </DataTable>
      )}
      {query.hasNextPage ? (
        <div className="mt-3 flex justify-center">
          <Button
            type="button"
            variant="outline"
            disabled={query.isFetchingNextPage}
            aria-busy={query.isFetchingNextPage}
            className="h-9 rounded-[10px]"
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
