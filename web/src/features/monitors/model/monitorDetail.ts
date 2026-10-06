import type { CheckListResponse, CheckResult, MonitorStats } from '@/shared/api/resources';
import type { TextKey } from '@/shared/i18n/textKey';

export type TimingPhase = 'dns' | 'connect' | 'tls' | 'ttfb';

export const timingPhases: readonly TimingPhase[] = ['dns', 'connect', 'tls', 'ttfb'];

export const timingTextKeys: Record<TimingPhase, TextKey<'monitors'>> = {
  dns: 'monitors:detail.timings.dns',
  connect: 'monitors:detail.timings.connect',
  tls: 'monitors:detail.timings.tls',
  ttfb: 'monitors:detail.timings.ttfb',
};

export interface TimingSegment {
  phase: TimingPhase;
  milliseconds: number;
  percent: number;
}

export interface TimingBreakdown {
  total: number;
  segments: TimingSegment[];
}

const hundred = 100;

export function timingBreakdown(timings: MonitorStats['timings']): TimingBreakdown {
  const measured = timingPhases.flatMap((phase) => {
    const value = timings[`${phase}_ms`];
    return value === null ? [] : [{ phase, milliseconds: value }];
  });
  const total = measured.reduce((sum, segment) => sum + segment.milliseconds, 0);
  return {
    total,
    segments: measured.map((segment) => ({ ...segment, percent: total === 0 ? 0 : (segment.milliseconds / total) * hundred })),
  };
}

const failureKinds = ['timeout', 'dns', 'connect', 'tls', 'http_status', 'too_many_redirects', 'blocked_target', 'body_too_large', 'unknown'] as const;

type FailureKind = (typeof failureKinds)[number];

const failureTextKeys: Record<Exclude<FailureKind, 'http_status'>, TextKey<'monitors'>> = {
  timeout: 'monitors:failures.timeout',
  dns: 'monitors:failures.dns',
  connect: 'monitors:failures.connect',
  tls: 'monitors:failures.tls',
  too_many_redirects: 'monitors:failures.too_many_redirects',
  blocked_target: 'monitors:failures.blocked_target',
  body_too_large: 'monitors:failures.body_too_large',
  unknown: 'monitors:failures.unknown',
};

export interface CheckDetailText {
  key: TextKey<'monitors'>;
  status: number | null;
}

export function checkDetailText(check: Pick<CheckResult, 'outcome' | 'failure_kind' | 'http_status'>): CheckDetailText {
  if (check.failure_kind === null || check.failure_kind === 'http_status') {
    return check.http_status === null ? { key: 'monitors:failures.no_status', status: null } : { key: 'monitors:failures.http_status', status: check.http_status };
  }
  const kind = failureKinds.find((candidate) => candidate === check.failure_kind) ?? 'unknown';
  return { key: kind === 'http_status' ? 'monitors:failures.no_status' : failureTextKeys[kind], status: null };
}

export function checksOf(pages: readonly CheckListResponse[] | undefined): CheckResult[] {
  return pages === undefined ? [] : pages.flatMap((page) => page.data);
}

export const checkTimeFormat: Intl.DateTimeFormatOptions = { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
