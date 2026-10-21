import { describe, expect, it } from 'vitest';
import { orderedTimeline, pageWindow, timelineEntry } from '@/features/incidents/model/timeline';
import type { TimelineEvent } from '@/shared/api/resources';

function event(overrides: Partial<TimelineEvent>): TimelineEvent {
  return {
    kind: 'opened',
    occurred_at: '2026-10-29T10:00:00Z',
    failure_kind: null,
    http_status: null,
    message: null,
    duration_seconds: null,
    channel_id: null,
    channel_type: null,
    alert_kind: null,
    attempts: null,
    ...overrides,
  };
}

describe('timelineEntry', () => {
  it('reads the cause of the opening', () => {
    expect(timelineEntry(event({ kind: 'opened', failure_kind: 'http_status', http_status: 503, message: 'Service Unavailable' }))).toEqual({
      kind: 'opened',
      tone: 'alert',
      cause: { key: 'incidents:cause.http_status', status: 503 },
      message: 'Service Unavailable',
    });
  });

  it('reads a keyword failure as the cause of the opening', () => {
    expect(timelineEntry(event({ kind: 'opened', failure_kind: 'keyword_missing', http_status: 200, message: 'the keyword is missing from the response body' }))).toEqual({
      kind: 'opened',
      tone: 'alert',
      cause: { key: 'incidents:cause.keyword_missing', status: null },
      message: 'the keyword is missing from the response body',
    });
  });

  it('reads the downtime of the resolution', () => {
    expect(timelineEntry(event({ kind: 'resolved', duration_seconds: 252 }))).toEqual({ kind: 'resolved', tone: 'positive', durationSeconds: 252 });
  });

  it('names the alerts by kind, channel and outcome', () => {
    expect(timelineEntry(event({ kind: 'alert_sent', channel_type: 'discord', alert_kind: 'down', attempts: 1 }))).toEqual({
      kind: 'alert',
      tone: 'neutral',
      textKey: 'incidents:timeline.down_sent',
      channel: 'incidents:channels.discord',
      attempts: 1,
    });
    expect(timelineEntry(event({ kind: 'alert_failed', channel_type: 'pager', alert_kind: 'up', attempts: 5 }))).toMatchObject({
      tone: 'warning',
      textKey: 'incidents:timeline.recovery_failed',
      channel: 'incidents:channels.unknown',
      attempts: 5,
    });
  });
});

describe('orderedTimeline', () => {
  it('puts the oldest event first', () => {
    const events = [event({ kind: 'resolved', occurred_at: '2026-10-29T10:05:00Z' }), event({ kind: 'opened', occurred_at: '2026-10-29T10:00:00Z' })];

    expect(orderedTimeline(events).map((item) => item.kind)).toEqual(['opened', 'resolved']);
  });
});

describe('pageWindow', () => {
  it('knows when there is a page before and after', () => {
    expect(pageWindow({ current_page: 1, last_page: 1 })).toEqual({ page: 1, lastPage: 1, hasPrevious: false, hasNext: false });
    expect(pageWindow({ current_page: 2, last_page: 3 })).toEqual({ page: 2, lastPage: 3, hasPrevious: true, hasNext: true });
  });
});
