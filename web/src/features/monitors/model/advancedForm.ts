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

export interface AdvancedTexts {
  required: string;
  maxLength: (max: number) => string;
  headerName: string;
  headerRefused: string;
  headerDuplicate: string;
  headerValue: string;
  tooManyHeaders: (max: number) => string;
  threshold: (bounds: { min: number; max: number }) => string;
}

export interface FormIssue {
  path: (string | number)[];
  message: string;
}

const headerTokenPattern = /^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/;
const wholeNumberPattern = /^\d+$/;
const firstPrintableCode = 0x20;
const deleteCode = 0x7f;

function characterCount(text: string): number {
  return Array.from(text).length;
}

function hasControlCharacter(text: string): boolean {
  return Array.from(text).some((character) => {
    const code = character.codePointAt(0) ?? firstPrintableCode;
    return code < firstPrintableCode || code === deleteCode;
  });
}

function keywordIssues(values: AdvancedValues, method: Method, monitoring: Monitoring, texts: AdvancedTexts): FormIssue[] {
  const max = monitoring.lengths.keyword.max;
  if (!keywordApplies(method) || characterCount(values.keyword.trim()) <= max) {
    return [];
  }
  return [{ path: ['keyword'], message: texts.maxLength(max) }];
}

function headerNameIssue(name: string, seen: Set<string>, monitoring: Monitoring, texts: AdvancedTexts): string | null {
  const max = monitoring.lengths.request_header_name.max;
  const lowerName = name.toLowerCase();
  if (name === '') {
    return texts.required;
  }
  if (characterCount(name) > max) {
    return texts.maxLength(max);
  }
  if (!headerTokenPattern.test(name)) {
    return texts.headerName;
  }
  if (monitoring.refused_header_names.some((refused) => refused.toLowerCase() === lowerName)) {
    return texts.headerRefused;
  }
  if (seen.has(lowerName)) {
    return texts.headerDuplicate;
  }
  seen.add(lowerName);
  return null;
}

function headerValueIssue(value: string, monitoring: Monitoring, texts: AdvancedTexts): string | null {
  const max = monitoring.lengths.request_header_value.max;
  if (value === '') {
    return texts.required;
  }
  if (hasControlCharacter(value)) {
    return texts.headerValue;
  }
  return characterCount(value) > max ? texts.maxLength(max) : null;
}

function headerIssues(values: AdvancedValues, monitoring: Monitoring, texts: AdvancedTexts): FormIssue[] {
  if (!values.replace_headers) {
    return [];
  }
  const max = monitoring.limits.max_request_headers;
  const seen = new Set<string>();
  const rowIssues = values.request_headers.flatMap((row, index): FormIssue[] => {
    const nameIssue = headerNameIssue(row.name.trim(), seen, monitoring, texts);
    const valueIssue = headerValueIssue(row.value.trim(), monitoring, texts);
    return [
      ...(nameIssue === null ? [] : [{ path: ['request_headers', index, 'name'], message: nameIssue }]),
      ...(valueIssue === null ? [] : [{ path: ['request_headers', index, 'value'], message: valueIssue }]),
    ];
  });
  return values.request_headers.length > max ? [{ path: ['request_headers'], message: texts.tooManyHeaders(max) }, ...rowIssues] : rowIssues;
}

function bodyIssues(values: AdvancedValues, method: Method, monitoring: Monitoring, texts: AdvancedTexts): FormIssue[] {
  const max = monitoring.lengths.request_body.max;
  if (!values.replace_body || !bodyApplies(method, monitoring) || characterCount(values.request_body) <= max) {
    return [];
  }
  return [{ path: ['request_body'], message: texts.maxLength(max) }];
}

function thresholdIssues(values: AdvancedValues, monitoring: Monitoring, texts: AdvancedTexts): FormIssue[] {
  const { min, max } = monitoring.thresholds;
  const fields = ['failure_threshold', 'recovery_threshold'] as const;
  return fields.flatMap((field) => {
    const text = values[field].trim();
    const count = Number(text);
    return wholeNumberPattern.test(text) && count >= min && count <= max ? [] : [{ path: [field], message: texts.threshold({ min, max }) }];
  });
}

export function advancedIssues(values: AdvancedValues, method: Method, monitoring: Monitoring, texts: AdvancedTexts): FormIssue[] {
  return [
    ...keywordIssues(values, method, monitoring, texts),
    ...headerIssues(values, monitoring, texts),
    ...bodyIssues(values, method, monitoring, texts),
    ...thresholdIssues(values, monitoring, texts),
  ];
}
