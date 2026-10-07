import { Link } from '@tanstack/react-router';
import { useTranslation } from 'react-i18next';
import { useIncidents } from '@/features/incidents/api/incidents';
import { causeText, incidentDurationSeconds, incidentStatus, startedFormat, statusTextKeys } from '@/features/incidents/model/incidents';
import type { Incident } from '@/shared/api/resources';
import { formatDate } from '@/shared/time/format';
import { useDurationText } from '@/shared/time/useDurationText';
import { ChevronRight } from '@/shared/ui/icons';
import { Skeleton } from '@/shared/ui/skeleton';
import { CardHeading, Pill, SurfaceCard } from '@/shared/ui/surface-card';

interface MonitorIncidentsCardProps {
  organizationId: string;
  monitorId: string;
  now: Date;
  size: number;
}

function IncidentItem({ incident, now }: { incident: Incident; now: Date }) {
  const { t, i18n } = useTranslation(['incidents']);
  const durationText = useDurationText();
  const status = incidentStatus(incident);
  const cause = causeText(incident.cause);
  const seconds = incidentDurationSeconds(incident, now);
  return (
    <li>
      <Link
        to="/incidents/$incidentId"
        params={{ incidentId: incident.id }}
        className="flex items-center gap-3 rounded-[13px] border-2 border-divider bg-surface-raised px-3.5 py-2.5 hover:bg-secondary"
      >
        <span className="grid min-w-0 flex-1 gap-0.5">
          <span className="truncate">{cause.status === null ? t(cause.key) : t(cause.key, { status: cause.status })}</span>
          <span className="truncate text-sm text-muted-foreground">
            {incident.started_at === null ? '' : formatDate(incident.started_at, { locale: i18n.language }, startedFormat)}
            {seconds === null ? '' : t('incidents:monitor.for', { duration: durationText(seconds) })}
          </span>
        </span>
        <Pill tone={status === 'open' ? 'alert' : 'positive'}>{t(statusTextKeys[status])}</Pill>
        <ChevronRight className="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
      </Link>
    </li>
  );
}

export function MonitorIncidentsCard({ organizationId, monitorId, now, size }: MonitorIncidentsCardProps) {
  const { t } = useTranslation(['incidents']);
  const incidents = useIncidents(organizationId, { monitor: monitorId, perPage: size }).data;
  return (
    <SurfaceCard aria-labelledby="monitor-incidents-title" className="p-4 sm:p-5">
      <CardHeading id="monitor-incidents-title" title={t('incidents:monitor.title')} />
      <div className="mt-3">
        {incidents === undefined ? (
          <Skeleton className="h-[110px] w-full" />
        ) : incidents.data.length === 0 ? (
          <p className="py-4 text-muted-foreground">{t('incidents:monitor.empty')}</p>
        ) : (
          <ul className="grid gap-2.5">
            {incidents.data.map((incident) => (
              <IncidentItem key={incident.id} incident={incident} now={now} />
            ))}
          </ul>
        )}
      </div>
      {incidents !== undefined && incidents.meta.total > incidents.data.length ? (
        <p className="mt-3 text-sm text-muted-foreground">{t('incidents:monitor.more', { count: incidents.meta.total - incidents.data.length })}</p>
      ) : null}
    </SurfaceCard>
  );
}
