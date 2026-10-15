import { Link } from '@tanstack/react-router';
import { cn } from 'cn';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { useIncident } from '@/features/incidents/api/incidents';
import { causeText, incidentDurationSeconds, incidentStatus, statusTextKeys } from '@/features/incidents/model/incidents';
import { orderedTimeline, timelineEntry, type TimelineTone } from '@/features/incidents/model/timeline';
import { isFailure } from '@/shared/api/errors';
import type { Incident, TimelineEvent } from '@/shared/api/resources';
import { formatDate } from '@/shared/time/format';
import { useDurationText } from '@/shared/time/useDurationText';
import { ArrowLeft } from '@/shared/ui/icons';
import { Skeleton } from '@/shared/ui/skeleton';
import { CardHeading, Pill, SurfaceCard } from '@/shared/ui/surface-card';
import { retryOf } from '@/shared/api/retry';
import { LoadError } from '@/shared/ui/load-error';

interface IncidentDetailPageProps {
  organizationId: string;
  incidentId: string;
  now: Date;
  notFound: ReactNode;
}

const dateTime: Intl.DateTimeFormatOptions = { dateStyle: 'medium', timeStyle: 'medium' };

const toneDots: Record<TimelineTone, string> = {
  alert: 'bg-alert',
  positive: 'bg-positive',
  neutral: 'bg-quiet',
  warning: 'bg-warning',
};

function TimelineItem({ event }: { event: TimelineEvent }) {
  const { t, i18n } = useTranslation(['incidents']);
  const durationText = useDurationText();
  const entry = timelineEntry(event);
  let title: string;
  let detail: string | null = null;
  switch (entry.kind) {
    case 'opened':
      title = t('incidents:timeline.opened');
      detail = [entry.cause.status === null ? t(entry.cause.key) : t(entry.cause.key, { status: entry.cause.status }), entry.message].filter((part) => part !== null).join(' · ');
      break;
    case 'resolved':
      title = t('incidents:timeline.resolved');
      detail = entry.durationSeconds === null ? null : t('incidents:timeline.resolved_after', { duration: durationText(entry.durationSeconds) });
      break;
    case 'alert':
      title = t(entry.textKey, { channel: t(entry.channel) });
      detail = t('incidents:timeline.attempts', { count: entry.attempts });
      break;
  }
  return (
    <li className="relative grid gap-1 pb-6 pl-8 last:pb-0">
      <span aria-hidden="true" className={cn('absolute top-1.5 left-1 size-3 rounded-full ring-4 ring-surface', toneDots[entry.tone])} />
      <span className="text-base">{title}</span>
      {detail === null || detail === '' ? null : <span className="text-sm text-muted-foreground">{detail}</span>}
      {event.occurred_at === null ? null : (
        <time dateTime={event.occurred_at} className="text-sm text-faint">
          {formatDate(event.occurred_at, { locale: i18n.language }, dateTime)}
        </time>
      )}
    </li>
  );
}

function IncidentSummary({ incident, now }: { incident: Incident; now: Date }) {
  const { t, i18n } = useTranslation(['incidents']);
  const durationText = useDurationText();
  const status = incidentStatus(incident);
  const cause = causeText(incident.cause);
  const seconds = incidentDurationSeconds(incident, now);
  const context = { locale: i18n.language };
  const name = incident.monitor.name ?? t('incidents:deleted_monitor');
  const facts = [
    { key: 'started', label: t('incidents:recent.started'), value: incident.started_at === null ? null : formatDate(incident.started_at, context, dateTime) },
    { key: 'resolved', label: t('incidents:list.resolved'), value: incident.resolved_at === null ? t('incidents:detail.not_yet') : formatDate(incident.resolved_at, context, dateTime) },
    { key: 'duration', label: t('incidents:recent.duration'), value: seconds === null ? null : durationText(seconds) },
    { key: 'cause', label: t('incidents:recent.cause'), value: cause.status === null ? t(cause.key) : t(cause.key, { status: cause.status }) },
  ];
  return (
    <SurfaceCard aria-labelledby="incident-title" className="p-4 sm:p-5">
      <div className="flex flex-wrap items-center gap-3">
        <h2 id="incident-title" className="text-[25px] leading-[32px] font-normal tracking-[-0.4px]">
          {incident.monitor.name === null ? (
            name
          ) : (
            <Link to="/monitors/$monitorId" params={{ monitorId: incident.monitor.id }} className="inline-flex min-h-10 items-center hover:text-primary">
              {name}
            </Link>
          )}
        </h2>
        <Pill tone={status === 'open' ? 'alert' : 'positive'}>{t(statusTextKeys[status])}</Pill>
      </div>
      <dl className="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {facts.map((fact) => (
          <div key={fact.key} className="grid gap-0.5">
            <dt className="text-sm text-muted-foreground">{fact.label}</dt>
            <dd className="text-lg">{fact.value ?? ''}</dd>
          </div>
        ))}
      </dl>
      {incident.cause.message === null ? null : <p className="mt-4 rounded-[11px] bg-chip px-3.5 py-2.5 text-sm text-muted-foreground">{incident.cause.message}</p>}
    </SurfaceCard>
  );
}

export function IncidentDetailPage({ organizationId, incidentId, now, notFound }: IncidentDetailPageProps) {
  const { t } = useTranslation(['incidents']);
  const query = useIncident(organizationId, incidentId);
  if (isFailure(query.error, 'not_found')) {
    return notFound;
  }
  const incident = query.data;
  const retry = retryOf(query);
  return (
    <div className="grid gap-4 2xl:gap-5">
      <Link to="/incidents" className="flex min-h-10 w-fit items-center gap-2 text-muted-foreground hover:text-foreground">
        <ArrowLeft className="size-4" aria-hidden="true" />
        {t('incidents:detail.back')}
      </Link>
      {incident === undefined ? (
        retry === null ? (
          <Skeleton className="h-[160px] w-full" />
        ) : (
          <LoadError onRetry={retry} />
        )
      ) : (
        <>
          <IncidentSummary incident={incident} now={now} />
          <SurfaceCard aria-labelledby="timeline-title" className="p-4 sm:p-5">
            <CardHeading id="timeline-title" title={t('incidents:timeline.title')} />
            {(incident.timeline ?? []).length === 0 ? (
              <p className="mt-3 text-muted-foreground">{t('incidents:timeline.empty')}</p>
            ) : (
              <ol className="relative mt-4 before:absolute before:top-2 before:bottom-2 before:left-[9px] before:w-0.5 before:bg-line-strong">
                {orderedTimeline(incident.timeline ?? []).map((event, index) => (
                  <TimelineItem key={`${event.kind}-${event.occurred_at ?? ''}-${String(index)}`} event={event} />
                ))}
              </ol>
            )}
          </SurfaceCard>
        </>
      )}
    </div>
  );
}
