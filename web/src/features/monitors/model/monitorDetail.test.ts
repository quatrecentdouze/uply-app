import { describe, expect, it } from 'vitest';
import { checkDetailText, checksOf, timingBreakdown } from '@/features/monitors/model/monitorDetail';
import type { CheckResult } from '@/shared/api/resources';

function check(overrides: Partial<CheckResult> = {}): CheckResult {
  return {
    checked_at: '2026-10-29T12:00:00Z',
    outcome: 'up',
    failure_kind: null,
    http_status: 200,
    response_ms: 100,
    dns_ms: 5,
    connect_ms: 10,
    tls_ms: 20,
    ttfb_ms: 65,
    error_message: null,
    ...overrides,
  };
}

describe('timingBreakdown', () => {
  it('splits the average timings in shares of their sum', () => {
    expect(timingBreakdown({ dns_ms: 10, connect_ms: 30, tls_ms: null, ttfb_ms: 60 })).toEqual({
      total: 100,
      segments: [
        { phase: 'dns', milliseconds: 10, percent: 10 },
        { phase: 'connect', milliseconds: 30, percent: 30 },
        { phase: 'ttfb', milliseconds: 60, percent: 60 },
      ],
    });
  });

  it('has no segment without timings', () => {
    expect(timingBreakdown({ dns_ms: null, connect_ms: null, tls_ms: null, ttfb_ms: null })).toEqual({ total: 0, segments: [] });
  });
});

describe('checkDetailText', () => {
  it('shows the status code of an answer', () => {
    expect(checkDetailText(check())).toEqual({ key: 'monitors:failures.http_status', status: 200 });
    expect(checkDetailText(check({ outcome: 'down', failure_kind: 'http_status', http_status: 503 }))).toEqual({ key: 'monitors:failures.http_status', status: 503 });
  });

  it('names the failure of a check without an answer', () => {
    expect(checkDetailText(check({ outcome: 'down', failure_kind: 'timeout', http_status: null }))).toEqual({ key: 'monitors:failures.timeout', status: null });
    expect(checkDetailText(check({ outcome: 'down', failure_kind: 'http_status', http_status: null }))).toEqual({ key: 'monitors:failures.no_status', status: null });
  });

  it('names a keyword failure even with the status code of the answer', () => {
    expect(checkDetailText(check({ outcome: 'down', failure_kind: 'keyword_missing', http_status: 200 }))).toEqual({ key: 'monitors:failures.keyword_missing', status: null });
    expect(checkDetailText(check({ outcome: 'down', failure_kind: 'keyword_found', http_status: 200 }))).toEqual({ key: 'monitors:failures.keyword_found', status: null });
  });
});

describe('checksOf', () => {
  it('joins the pages in order', () => {
    const page = (checkedAt: string) => ({ data: [check({ checked_at: checkedAt })], links: { first: null, last: null, prev: null, next: null }, meta: { path: null, per_page: 1, next_cursor: null, prev_cursor: null } });

    expect(checksOf([page('b'), page('a')]).map((item) => item.checked_at)).toEqual(['b', 'a']);
    expect(checksOf(undefined)).toEqual([]);
  });
});
