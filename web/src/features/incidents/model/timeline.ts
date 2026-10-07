import { causeText, type CauseText } from '@/features/incidents/model/incidents';
import type { TimelineEvent } from '@/shared/api/resources';
import type { TextKey } from '@/shared/i18n/textKey';

export type TimelineTone = 'alert' | 'positive' | 'neutral' | 'warning';

export type ChannelKind = 'email' | 'discord' | 'slack' | 'unknown';

export type TimelineEntry =
  | { kind: 'opened'; tone: TimelineTone; cause: CauseText; message: string | null }
  | { kind: 'resolved'; tone: TimelineTone; durationSeconds: number | null }
  | { kind: 'alert'; tone: TimelineTone; textKey: TextKey<'incidents'>; channel: TextKey<'incidents'>; attempts: number };

const channelTextKeys: Record<ChannelKind, TextKey<'incidents'>> = {
  email: 'incidents:channels.email',
  discord: 'incidents:channels.discord',
  slack: 'incidents:channels.slack',
  unknown: 'incidents:channels.unknown',
};

function channelKind(type: string | null): ChannelKind {
  return type === 'email' || type === 'discord' || type === 'slack' ? type : 'unknown';
}

function alertTextKey(sent: boolean, alertKind: string | null): TextKey<'incidents'> {
  const recovery = alertKind === 'up';
  if (sent) {
    return recovery ? 'incidents:timeline.recovery_sent' : 'incidents:timeline.down_sent';
  }
  return recovery ? 'incidents:timeline.recovery_failed' : 'incidents:timeline.down_failed';
}

export function timelineEntry(event: TimelineEvent): TimelineEntry {
  switch (event.kind) {
    case 'opened':
      return {
        kind: 'opened',
        tone: 'alert',
        cause: causeText({ failure_kind: event.failure_kind, http_status: event.http_status, message: event.message }),
        message: event.message,
      };
    case 'resolved':
      return { kind: 'resolved', tone: 'positive', durationSeconds: event.duration_seconds };
    case 'alert_sent':
    case 'alert_failed': {
      const sent = event.kind === 'alert_sent';
      return {
        kind: 'alert',
        tone: sent ? 'neutral' : 'warning',
        textKey: alertTextKey(sent, event.alert_kind),
        channel: channelTextKeys[channelKind(event.channel_type)],
        attempts: event.attempts ?? 1,
      };
    }
  }
}

export function orderedTimeline(events: readonly TimelineEvent[]): TimelineEvent[] {
  return [...events].sort((left, right) => (left.occurred_at ?? '').localeCompare(right.occurred_at ?? ''));
}

export type IncidentFilter = 'all' | 'open' | 'resolved';

export const incidentFilters: readonly IncidentFilter[] = ['all', 'open', 'resolved'];

export const incidentFilterTextKeys: Record<IncidentFilter, TextKey<'incidents'>> = {
  all: 'incidents:list.filters.all',
  open: 'incidents:status.open',
  resolved: 'incidents:status.resolved',
};

export interface PageWindow {
  page: number;
  lastPage: number;
  hasPrevious: boolean;
  hasNext: boolean;
}

export function pageWindow(meta: { current_page: number; last_page: number }): PageWindow {
  return { page: meta.current_page, lastPage: meta.last_page, hasPrevious: meta.current_page > 1, hasNext: meta.current_page < meta.last_page };
}
