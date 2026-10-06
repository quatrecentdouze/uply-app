import { z } from 'zod';
import type { CreateMonitorBody, UpdateMonitorBody } from '@/features/monitors/api/monitors';
import type { Meta } from '@/shared/api/meta';
import type { Monitor } from '@/shared/api/resources';
import type { TextKey } from '@/shared/i18n/textKey';
import type { BaseValidationTexts } from '@/shared/i18n/useValidationTexts';

export type MonitoringMeta = Meta['monitoring'];

export type HttpMethod = Monitor['method'];

export interface MonitorFormTexts extends BaseValidationTexts {
  url: string;
  maxLength: (max: number) => string;
  statusRanges: string;
  tooManyRanges: (max: number) => string;
  interval: string;
  timeout: (bounds: { min: number; max: number }) => string;
}

export interface MonitorFormValues {
  name: string;
  url: string;
  method: HttpMethod;
  interval_seconds: string;
  timeout_seconds: string;
  accepted_status_codes: string;
  follow_redirects: boolean;
}

export const methodTextKeys: Record<HttpMethod, TextKey<'monitors'>> = {
  get: 'monitors:methods.get',
  head: 'monitors:methods.head',
};

export function knownMethods(methods: readonly string[]): HttpMethod[] {
  return methods.flatMap((method) => (method === 'get' || method === 'head' ? [method] : []));
}

const lowestStatus = 100;
const highestStatus = 599;
const statusRangePattern = /^(\d{3})(?:-(\d{3}))?$/;
const webSchemes = new Set(['http:', 'https:']);

function isStatusRange(token: string): boolean {
  const match = statusRangePattern.exec(token);
  if (match === null) {
    return false;
  }
  const start = Number(match[1]);
  const end = match[2] === undefined ? start : Number(match[2]);
  return start >= lowestStatus && end <= highestStatus && start <= end;
}

export function statusRangesOf(text: string): string[] {
  return text
    .split(/[\s,]+/)
    .map((token) => token.trim())
    .filter((token) => token !== '');
}

export function isWebUrl(value: string): boolean {
  if (!URL.canParse(value)) {
    return false;
  }
  return webSchemes.has(new URL(value).protocol);
}

export function monitorFormSchema(texts: MonitorFormTexts, monitoring: MonitoringMeta) {
  const { lengths, timeouts, intervals, limits } = monitoring;
  return z.object({
    name: z.string().trim().min(1, texts.required).max(lengths.monitor_name.max, texts.maxLength(lengths.monitor_name.max)),
    url: z.string().trim().min(1, texts.required).max(lengths.url.max, texts.maxLength(lengths.url.max)).refine((value) => value === '' || isWebUrl(value), texts.url),
    method: z.enum(['get', 'head']),
    interval_seconds: z.string().refine((value) => intervals.allowed_seconds.includes(Number(value)), texts.interval),
    timeout_seconds: z
      .string()
      .trim()
      .refine((value) => {
        const seconds = Number(value);
        return value !== '' && Number.isInteger(seconds) && seconds >= timeouts.min_seconds && seconds <= timeouts.max_seconds;
      }, texts.timeout({ min: timeouts.min_seconds, max: timeouts.max_seconds })),
    accepted_status_codes: z
      .string()
      .refine((value) => statusRangesOf(value).length > 0, texts.required)
      .refine((value) => statusRangesOf(value).every(isStatusRange), texts.statusRanges)
      .refine((value) => statusRangesOf(value).length <= limits.max_accepted_status_ranges, texts.tooManyRanges(limits.max_accepted_status_ranges)),
    follow_redirects: z.boolean(),
  });
}

export function monitorFormValues(monitor: Monitor | null, monitoring: MonitoringMeta): MonitorFormValues {
  if (monitor === null) {
    return {
      name: '',
      url: '',
      method: monitoring.default_method,
      interval_seconds: String(monitoring.intervals.default_seconds),
      timeout_seconds: String(monitoring.timeouts.default_seconds),
      accepted_status_codes: monitoring.default_accepted_status_codes.join(', '),
      follow_redirects: true,
    };
  }
  return {
    name: monitor.name,
    url: monitor.url,
    method: monitor.method,
    interval_seconds: String(monitor.interval_seconds),
    timeout_seconds: String(monitor.timeout_seconds),
    accepted_status_codes: monitor.accepted_status_codes.join(', '),
    follow_redirects: monitor.follow_redirects,
  };
}

export function createMonitorBody(values: MonitorFormValues): CreateMonitorBody {
  return {
    name: values.name.trim(),
    url: values.url.trim(),
    method: values.method,
    interval_seconds: Number(values.interval_seconds),
    timeout_seconds: Number(values.timeout_seconds),
    accepted_status_codes: statusRangesOf(values.accepted_status_codes),
    follow_redirects: values.follow_redirects,
  };
}

function sameList(left: readonly string[], right: readonly string[]): boolean {
  return left.length === right.length && left.every((item, index) => item === right[index]);
}

export function updateMonitorBody(monitor: Monitor, values: MonitorFormValues): UpdateMonitorBody {
  const next = createMonitorBody(values);
  return {
    ...(next.name === monitor.name ? {} : { name: next.name }),
    ...(next.url === monitor.url ? {} : { url: next.url }),
    ...(next.method === monitor.method ? {} : { method: next.method }),
    ...(next.interval_seconds === monitor.interval_seconds ? {} : { interval_seconds: next.interval_seconds }),
    ...(next.timeout_seconds === monitor.timeout_seconds ? {} : { timeout_seconds: next.timeout_seconds }),
    ...(sameList(next.accepted_status_codes ?? [], monitor.accepted_status_codes) ? {} : { accepted_status_codes: next.accepted_status_codes }),
    ...(next.follow_redirects === monitor.follow_redirects ? {} : { follow_redirects: next.follow_redirects }),
  };
}

export function hasChanges(body: UpdateMonitorBody): boolean {
  return Object.keys(body).length > 0;
}

const secondsPerMinute = 60;
const secondsPerHour = 3600;

export interface IntervalText {
  key: TextKey<'monitors'>;
  count: number;
}

export function intervalText(seconds: number): IntervalText {
  if (seconds % secondsPerHour === 0) {
    return { key: 'monitors:form.every_hours', count: seconds / secondsPerHour };
  }
  if (seconds % secondsPerMinute === 0) {
    return { key: 'monitors:form.every_minutes', count: seconds / secondsPerMinute };
  }
  return { key: 'monitors:form.every_seconds', count: seconds };
}
