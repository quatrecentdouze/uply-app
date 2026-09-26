export interface TimeFormatContext {
  locale: string;
  timeZone?: string;
}

const secondsPerMinute = 60;
const secondsPerHour = 3600;
const secondsPerDay = 86400;

function dateTimeFormat(context: TimeFormatContext, options: Intl.DateTimeFormatOptions): Intl.DateTimeFormat {
  return new Intl.DateTimeFormat(context.locale, context.timeZone === undefined ? options : { ...options, timeZone: context.timeZone });
}

export function formatDateTime(iso: string, context: TimeFormatContext): string {
  return dateTimeFormat(context, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
}

export function formatDate(iso: string, context: TimeFormatContext, options: Intl.DateTimeFormatOptions): string {
  return dateTimeFormat(context, options).format(new Date(iso));
}

export interface RelativeTime {
  value: number;
  unit: Intl.RelativeTimeFormatUnit;
}

export function relativeTime(iso: string, now: Date): RelativeTime {
  const seconds = Math.round((new Date(iso).getTime() - now.getTime()) / 1000);
  const size = Math.abs(seconds);
  if (size < secondsPerMinute) {
    return { value: seconds, unit: 'second' };
  }
  if (size < secondsPerHour) {
    return { value: Math.trunc(seconds / secondsPerMinute), unit: 'minute' };
  }
  if (size < secondsPerDay) {
    return { value: Math.trunc(seconds / secondsPerHour), unit: 'hour' };
  }
  return { value: Math.trunc(seconds / secondsPerDay), unit: 'day' };
}

export function formatRelative(iso: string, now: Date, context: TimeFormatContext): string {
  const { value, unit } = relativeTime(iso, now);
  return new Intl.RelativeTimeFormat(context.locale, { numeric: 'auto' }).format(value, unit);
}

export type DurationParts =
  | { kind: 'seconds'; seconds: number }
  | { kind: 'minutes'; minutes: number; seconds: number }
  | { kind: 'hours'; hours: number; minutes: number }
  | { kind: 'days'; days: number; hours: number };

export function durationParts(totalSeconds: number): DurationParts {
  const seconds = Math.max(0, Math.floor(totalSeconds));
  if (seconds < secondsPerMinute) {
    return { kind: 'seconds', seconds };
  }
  if (seconds < secondsPerHour) {
    return { kind: 'minutes', minutes: Math.floor(seconds / secondsPerMinute), seconds: seconds % secondsPerMinute };
  }
  if (seconds < secondsPerDay) {
    return { kind: 'hours', hours: Math.floor(seconds / secondsPerHour), minutes: Math.floor((seconds % secondsPerHour) / secondsPerMinute) };
  }
  return { kind: 'days', days: Math.floor(seconds / secondsPerDay), hours: Math.floor((seconds % secondsPerDay) / secondsPerHour) };
}

export function secondsBetween(fromIso: string, to: Date): number {
  return Math.max(0, Math.floor((to.getTime() - new Date(fromIso).getTime()) / 1000));
}
