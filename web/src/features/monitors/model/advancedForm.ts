import type { Meta } from '@/shared/api/meta';
import type { Monitor } from '@/shared/api/resources';

type Monitoring = Meta['monitoring'];

type Method = Monitor['method'];

export type KeywordMatch = Monitor['keyword_match'];

export const keywordMatches = ['present', 'absent'] as const satisfies readonly KeywordMatch[];

export interface HeaderRow {
  name: string;
  value: string;
}

export interface AdvancedValues {
  keyword: string;
  keyword_match: KeywordMatch;
  replace_headers: boolean;
  request_headers: HeaderRow[];
  replace_body: boolean;
  request_body: string;
  failure_threshold: string;
  recovery_threshold: string;
}

export const advancedFields = ['keyword', 'keyword_match', 'request_headers', 'request_body', 'failure_threshold', 'recovery_threshold'] as const;

const methodsWithoutResponseBody: readonly Method[] = ['head'];

export function keywordApplies(method: Method): boolean {
  return !methodsWithoutResponseBody.includes(method);
}

export function bodyApplies(method: Method, monitoring: Monitoring): boolean {
  return monitoring.body_methods.includes(method);
}

function isKeywordMatch(match: string): match is KeywordMatch {
  return keywordMatches.some((candidate) => candidate === match);
}

export function knownKeywordMatches(matches: readonly string[]): KeywordMatch[] {
  return matches.filter(isKeywordMatch);
}

export function advancedValues(monitor: Monitor | null, monitoring: Monitoring): AdvancedValues {
  if (monitor === null) {
    return {
      keyword: '',
      keyword_match: monitoring.default_keyword_match,
      replace_headers: true,
      request_headers: [],
      replace_body: true,
      request_body: '',
      failure_threshold: String(monitoring.thresholds.default_failure),
      recovery_threshold: String(monitoring.thresholds.default_recovery),
    };
  }
  return {
    keyword: monitor.keyword ?? '',
    keyword_match: monitor.keyword_match,
    replace_headers: monitor.request_header_names.length === 0,
    request_headers: [],
    replace_body: !monitor.has_request_body,
    request_body: '',
    failure_threshold: String(monitor.failure_threshold),
    recovery_threshold: String(monitor.recovery_threshold),
  };
}

export function advancedOpenAtStart(monitor: Monitor | null, monitoring: Monitoring): boolean {
  if (monitor === null) {
    return false;
  }
  return (
    monitor.keyword !== null ||
    monitor.request_header_names.length > 0 ||
    monitor.has_request_body ||
    monitor.failure_threshold !== monitoring.thresholds.default_failure ||
    monitor.recovery_threshold !== monitoring.thresholds.default_recovery
  );
}

export function replacingHeaderRows(names: readonly string[]): HeaderRow[] {
  return names.map((name) => ({ name, value: '' }));
}

export function touchesAdvanced(fields: Iterable<string>): boolean {
  return [...fields].some((field) => advancedFields.some((advanced) => field === advanced || field.startsWith(`${advanced}.`)));
}
