import { Link } from '@tanstack/react-router';
import { useTranslation } from 'react-i18next';
import { causeText, incidentDurationSeconds, incidentStatus, startedFormat, statusTextKeys } from '@/features/incidents/model/incidents';
import type { Incident } from '@/shared/api/resources';
import { formatDate } from '@/shared/time/format';
import { useDurationText } from '@/shared/time/useDurationText';
import { Skeleton } from '@/shared/ui/skeleton';
import { CardHeading, Pill, SurfaceCard } from '@/shared/ui/surface-card';
import type { Retry } from '@/shared/api/retry';
import { LoadError } from '@/shared/ui/load-error';

interface RecentIncidentsCardProps {
  incidents: readonly Incident[] | undefined;
  now: Date;
  retry?: Retry;
}

const headCell = 'h-[35px] border-y-2 border-line-strong bg-chip px-2.5 text-sm font-light whitespace-nowrap text-quiet 2xl:px-4';

function IncidentRow({ incident, now }: { incident: Incident; now: Date }) {
  const { t, i18n } = useTranslation(['incidents']);
  const durationText = useDurationText();
  const status = incidentStatus(incident);
  const cause = causeText(incident.cause);
  const seconds = incidentDurationSeconds(incident, now);
  const name = incident.monitor.name ?? t('incidents:deleted_monitor');
  return (
    <tr className="h-[51px] text-base">
      <td className="max-w-0 truncate pr-2.5 pl-[18px]">
        <Link to="/incidents/$incidentId" params={{ incidentId: incident.id }} aria-label={t('incidents:recent.open', { name })} className="flex min-h-10 items-center hover:text-primary">
          {name}
        </Link>
      </td>
      <td className="px-2.5 whitespace-nowrap text-muted-foreground max-md:hidden 2xl:px-4">{incident.started_at === null ? '' : formatDate(incident.started_at, { locale: i18n.language }, startedFormat)}</td>
      <td className="px-2.5 whitespace-nowrap 2xl:px-4">{seconds === null ? '' : durationText(seconds)}</td>
      <td className="truncate px-2.5 text-muted-foreground max-md:hidden 2xl:px-4">{cause.status === null ? t(cause.key) : t(cause.key, { status: cause.status })}</td>
      <td className="pr-4 pl-2.5 text-right">
        <Pill tone={status === 'open' ? 'alert' : 'positive'}>{t(statusTextKeys[status])}</Pill>
      </td>
    </tr>
  );
}

export function RecentIncidentsCard({ incidents, now, retry = null }: RecentIncidentsCardProps) {
  const { t } = useTranslation(['incidents']);
  return (
    <SurfaceCard aria-labelledby="incidents-title" className="overflow-x-auto px-2.5 pt-3.5 pb-2 sm:px-5 sm:pt-4">
      <div className="sticky left-0 mb-[13px]">
        <CardHeading
          id="incidents-title"
          title={t('incidents:recent.title')}
          action={
            <Link to="/incidents" className="flex h-10 items-center md:h-[35px] rounded-[14px] bg-chip px-[13px] text-sm">
              {t('incidents:recent.view_all')}
            </Link>
          }
        />
      </div>
      {incidents === undefined ? (
        retry === null ? <Skeleton className="h-[120px] w-full" /> : <LoadError onRetry={retry} />
      ) : incidents.length === 0 ? (
        <p className="py-6 text-center text-muted-foreground">{t('incidents:recent.empty')}</p>
      ) : (
        <table className="w-full table-fixed border-separate border-spacing-0 text-left md:min-w-[620px]">
          <thead>
            <tr>
              <th scope="col" className={`${headCell} w-[38%] rounded-l-[10px] border-l-2 pl-[18px] md:w-[27%]`}>
                {t('incidents:recent.monitor')}
              </th>
              <th scope="col" className={`${headCell} w-[20%] max-md:hidden`}>
                {t('incidents:recent.started')}
              </th>
              <th scope="col" className={`${headCell} w-[26%] md:w-[17%]`}>
                {t('incidents:recent.duration')}
              </th>
              <th scope="col" className={`${headCell} w-[19%] max-md:hidden`}>
                {t('incidents:recent.cause')}
              </th>
              <th scope="col" className={`${headCell} w-[36%] rounded-r-[10px] border-r-2 pr-4 text-right md:w-[17%]`}>
                {t('incidents:recent.status')}
              </th>
            </tr>
          </thead>
          <tbody>
            {incidents.map((incident) => (
              <IncidentRow key={incident.id} incident={incident} now={now} />
            ))}
          </tbody>
        </table>
      )}
    </SurfaceCard>
  );
}
