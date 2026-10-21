import { describe, expect, it } from 'vitest';
import { causeText, incidentDurationSeconds, incidentStatus } from '@/features/incidents/model/incidents';
import { incidentFixture } from '@/shared/testing/fixtures';

const now = new Date('2026-10-29T10:05:00Z');

describe('incidentStatus', () => {
  it('reads an incident without end as ongoing', () => {
    expect(incidentStatus(incidentFixture())).toBe('open');
    expect(incidentStatus(incidentFixture({ resolved_at: '2026-10-29T10:02:00Z' }))).toBe('resolved');
  });
});

describe('incidentDurationSeconds', () => {
  it('uses the duration of a resolved incident', () => {
    expect(incidentDurationSeconds(incidentFixture({ resolved_at: '2026-10-29T10:02:00Z', duration_seconds: 120 }), now)).toBe(120);
  });

  it('counts an ongoing incident up to now', () => {
    expect(incidentDurationSeconds(incidentFixture({ started_at: '2026-10-29T10:00:00Z' }), now)).toBe(300);
  });

  it('knows no duration without a start', () => {
    expect(incidentDurationSeconds(incidentFixture({ started_at: null }), now)).toBeNull();
  });
});

describe('causeText', () => {
  it('names the HTTP status', () => {
    expect(causeText({ failure_kind: 'http_status', http_status: 503, message: null })).toEqual({ key: 'incidents:cause.http_status', status: 503 });
    expect(causeText({ failure_kind: 'http_status', http_status: null, message: null })).toEqual({ key: 'incidents:cause.http_error', status: null });
  });

  it('names the other failure kinds and falls back on unknown', () => {
    expect(causeText({ failure_kind: 'timeout', http_status: null, message: 'x' }).key).toBe('incidents:cause.timeout');
    expect(causeText({ failure_kind: 'brand_new', http_status: null, message: null }).key).toBe('incidents:cause.unknown');
    expect(causeText({ failure_kind: null, http_status: null, message: null }).key).toBe('incidents:cause.unknown');
  });

  it('names the keyword failures, not the status of their answer', () => {
    expect(causeText({ failure_kind: 'keyword_missing', http_status: 200, message: 'the keyword is missing from the response body' })).toEqual({
      key: 'incidents:cause.keyword_missing',
      status: null,
    });
    expect(causeText({ failure_kind: 'keyword_found', http_status: 200, message: null })).toEqual({ key: 'incidents:cause.keyword_found', status: null });
  });
});
