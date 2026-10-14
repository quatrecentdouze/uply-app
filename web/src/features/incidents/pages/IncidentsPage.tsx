import { Link } from '@tanstack/react-router';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useIncidents } from '@/features/incidents/api/incidents';
import { causeText, incidentDurationSeconds, incidentStatus, startedFormat, statusTextKeys } from '@/features/incidents/model/incidents';
import { type IncidentFilter, incidentFilters, incidentFilterTextKeys, pageWindow } from '@/features/incidents/model/timeline';
import type { Incident } from '@/shared/api/resources';
import { formatDate } from '@/shared/time/format';
import { useDurationText } from '@/shared/time/useDurationText';
import { Button } from '@/shared/ui/button';
import { cellClass, DataTable, rowClass } from '@/shared/ui/data-table';
import { Skeleton } from '@/shared/ui/skeleton';
import { Pill, SurfaceCard } from '@/shared/ui/surface-card';
import { retryOf } from '@/shared/api/retry';
import { LoadError } from '@/shared/ui/load-error';

interface IncidentsPageProps {
  organizationId: string;
  now: Date;
}

function IncidentRow({ incident, now }: { incident: Incident; now: Date }) {
  const { t, i18n } = useTranslation(['incidents']);
  const durationText = useDurationText();
  const status = incidentStatus(incident);
  const cause = causeText(incident.cause);
  const seconds = incidentDurationSeconds(incident, now);
  const name = incident.monitor.name ?? t('incidents:deleted_monitor');
  const context = { locale: i18n.language };
  return (
    <tr className={rowClass}>
      <td className={`${cellClass} max-w-0 truncate pl-[18px]`}>
        <Link to="/incidents/$incidentId" params={{ incidentId: incident.id }} aria-label={t('incidents:recent.open', { name })} className="hover:text-primary">
          {name}
        </Link>
      </td>
      <td className={`${cellClass} whitespace-nowrap text-muted-foreground`}>{incident.started_at === null ? '' : formatDate(incident.started_at, context, startedFormat)}</td>
      <td className={`${cellClass} whitespace-nowrap text-muted-foreground`}>{incident.resolved_at === null ? '' : formatDate(incident.resolved_at, context, startedFormat)}</td>
      <td className={`${cellClass} whitespace-nowrap`}>{seconds === null ? '' : durationText(seconds)}</td>
      <td className={`${cellClass} truncate text-muted-foreground`}>{cause.status === null ? t(cause.key) : t(cause.key, { status: cause.status })}</td>
      <td className={`${cellClass} pr-4 text-right`}>
        <Pill tone={status === 'open' ? 'alert' : 'positive'}>{t(statusTextKeys[status])}</Pill>
      </td>
    </tr>
  );
}

export function IncidentsPage({ organizationId, now }: IncidentsPageProps) {
  const { t } = useTranslation(['incidents']);
  const [filter, setFilter] = useState<IncidentFilter>('all');
  const [page, setPage] = useState(1);
  const query = useIncidents(organizationId, { ...(filter === 'all' ? {} : { state: filter }), page });
  const incidents = query.data;
  const retry = retryOf(query);
  const pages = incidents === undefined ? null : pageWindow(incidents.meta);

  return (
    <SurfaceCard aria-labelledby="incidents-list-title" className="px-2.5 pt-4 pb-3 sm:px-5">
      <h2 id="incidents-list-title" className="sr-only">
        {t('incidents:list.title')}
      </h2>
      <div role="group" aria-label={t('incidents:list.filters.label')} className="mb-4 flex flex-wrap items-center gap-[5px]">
        {incidentFilters.map((candidate) => (
          <button
            key={candidate}
            type="button"
            aria-pressed={candidate === filter}
            onClick={() => {
              setFilter(candidate);
              setPage(1);
            }}
            className="h-[31px] rounded-[22px] px-[17px] text-base text-muted-foreground aria-pressed:bg-bright aria-pressed:text-ink"
          >
            {t(incidentFilterTextKeys[candidate])}
          </button>
        ))}
      </div>
      {incidents === undefined ? (
        retry === null ? (
          <Skeleton className="h-[220px] w-full" />
        ) : (
          <LoadError onRetry={retry} />
        )
      ) : incidents.data.length === 0 ? (
        <p className="py-10 text-center text-muted-foreground">{filter === 'all' ? t('incidents:recent.empty') : t('incidents:list.no_match')}</p>
      ) : (
        <DataTable
          label={t('incidents:list.table')}
          className="min-w-[860px] table-fixed"
          head={[
            { label: t('incidents:recent.monitor'), className: 'w-[24%]' },
            { label: t('incidents:recent.started'), className: 'w-[16%]' },
            { label: t('incidents:list.resolved'), className: 'w-[16%]' },
            { label: t('incidents:recent.duration'), className: 'w-[13%]' },
            { label: t('incidents:recent.cause'), className: 'w-[17%]' },
            { label: t('incidents:recent.status'), className: 'w-[14%] text-right' },
          ]}
        >
          {incidents.data.map((incident) => (
            <IncidentRow key={incident.id} incident={incident} now={now} />
          ))}
        </DataTable>
      )}
      {pages === null || pages.lastPage <= 1 ? null : (
        <nav aria-label={t('incidents:list.pages')} className="mt-3 flex items-center justify-end gap-3">
          <span className="text-sm text-muted-foreground">{t('incidents:list.page', { page: pages.page, last: pages.lastPage })}</span>
          <Button
            type="button"
            variant="outline"
            className="h-9 rounded-[10px]"
            disabled={!pages.hasPrevious}
            onClick={() => {
              setPage(pages.page - 1);
            }}
          >
            {t('incidents:list.previous')}
          </Button>
          <Button
            type="button"
            variant="outline"
            className="h-9 rounded-[10px]"
            disabled={!pages.hasNext}
            onClick={() => {
              setPage(pages.page + 1);
            }}
          >
            {t('incidents:list.next')}
          </Button>
        </nav>
      )}
    </SurfaceCard>
  );
}
