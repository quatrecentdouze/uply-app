import { useTranslation } from 'react-i18next';
import { incidentTiming } from '@/features/status-pages/model/publicPage';
import type { PublicIncident } from '@/shared/api/resources';
import { formatDateTime } from '@/shared/time/format';
import { useDurationText } from '@/shared/time/useDurationText';
import { Pill } from '@/shared/ui/surface-card';

interface PublicIncidentListProps {
  incidents: readonly PublicIncident[];
  days: number | null;
}

function IncidentItem({ incident }: { incident: PublicIncident }) {
  const { t, i18n } = useTranslation(['status-pages']);
  const durationText = useDurationText();
  const timing = incidentTiming(incident);
  return (
    <li className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1.5 border-b-2 border-divider py-3.5 last:border-b-0">
      <div className="min-w-0">
        <p className="truncate text-base">{incident.display_name}</p>
        <p className="text-sm text-quiet">{t('status-pages:public.started', { time: formatDateTime(incident.started_at, { locale: i18n.language }) })}</p>
      </div>
      {timing.kind === 'ongoing' ? (
        <Pill tone="alert">{t('status-pages:public.ongoing')}</Pill>
      ) : (
        <Pill tone="positive">{t('status-pages:public.resolved_after', { duration: durationText(timing.seconds) })}</Pill>
      )}
    </li>
  );
}

export function PublicIncidentList({ incidents, days }: PublicIncidentListProps) {
  const { t } = useTranslation(['status-pages']);
  return (
    <section aria-labelledby="public-incidents-title" className="rounded-card border-2 border-line bg-surface px-4 pt-4 pb-2 sm:px-5">
      <h2 id="public-incidents-title" className="text-lg font-normal">
        {days === null ? t('status-pages:public.incidents_recent') : t('status-pages:public.incidents_title', { count: days })}
      </h2>
      {incidents.length === 0 ? (
        <p className="py-5 text-muted-foreground">{days === null ? t('status-pages:public.incidents_none') : t('status-pages:public.incidents_empty', { count: days })}</p>
      ) : (
        <ul className="mt-1">
          {incidents.map((incident) => (
            <IncidentItem key={`${incident.display_name}-${incident.started_at}`} incident={incident} />
          ))}
        </ul>
      )}
    </section>
  );
}
