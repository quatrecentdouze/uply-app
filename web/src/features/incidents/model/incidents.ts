import type { Incident } from '@/shared/api/resources';
import type { TextKey } from '@/shared/i18n/textKey';
import { secondsBetween } from '@/shared/time/format';

export type IncidentStatus = 'open' | 'resolved';

export const statusTextKeys: Record<IncidentStatus, TextKey<'incidents'>> = {
  open: 'incidents:status.open',
  resolved: 'incidents:status.resolved',
};

export function incidentStatus(incident: Pick<Incident, 'resolved_at'>): IncidentStatus {
  return incident.resolved_at === null ? 'open' : 'resolved';
}

export function incidentDurationSeconds(incident: Pick<Incident, 'started_at' | 'resolved_at' | 'duration_seconds'>, now: Date): number | null {
  if (incident.duration_seconds !== null) {
    return incident.duration_seconds;
  }
  if (incident.started_at === null) {
    return null;
  }
  return incident.resolved_at === null ? secondsBetween(incident.started_at, now) : secondsBetween(incident.started_at, new Date(incident.resolved_at));
}

const failureKinds = ['timeout', 'dns', 'connect', 'tls', 'http_status', 'too_many_redirects', 'blocked_target', 'body_too_large', 'unknown'] as const;

type FailureKind = (typeof failureKinds)[number];

const causeTextKeys: Record<Exclude<FailureKind, 'http_status'>, TextKey<'incidents'>> = {
  timeout: 'incidents:cause.timeout',
  dns: 'incidents:cause.dns',
  connect: 'incidents:cause.connect',
  tls: 'incidents:cause.tls',
  too_many_redirects: 'incidents:cause.too_many_redirects',
  blocked_target: 'incidents:cause.blocked_target',
  body_too_large: 'incidents:cause.body_too_large',
  unknown: 'incidents:cause.unknown',
};

export type CauseText = { key: TextKey<'incidents'>; status: number | null };

function isFailureKind(kind: string): kind is FailureKind {
  return failureKinds.some((candidate) => candidate === kind);
}

export function causeText(cause: Incident['cause']): CauseText {
  const kind = cause.failure_kind !== null && isFailureKind(cause.failure_kind) ? cause.failure_kind : 'unknown';
  if (kind === 'http_status') {
    return cause.http_status === null ? { key: 'incidents:cause.http_error', status: null } : { key: 'incidents:cause.http_status', status: cause.http_status };
  }
  return { key: causeTextKeys[kind], status: null };
}
