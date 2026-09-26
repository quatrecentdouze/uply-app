import { describe, expect, it } from 'vitest';
import { durationParts, formatDate, formatDateTime, formatRelative, relativeTime, secondsBetween } from '@/shared/time/format';

const utc = { locale: 'en', timeZone: 'UTC' };
const now = new Date('2026-10-29T12:00:00Z');

describe('formatDateTime', () => {
  it('formats an ISO time in the given zone', () => {
    expect(formatDateTime('2026-10-29T09:05:00Z', utc)).toBe('Oct 29, 2026, 9:05 AM');
  });

  it('follows the time zone of the context', () => {
    expect(formatDateTime('2026-10-29T09:05:00Z', { locale: 'en', timeZone: 'Europe/Paris' })).toBe('Oct 29, 2026, 10:05 AM');
  });
});

describe('formatDate', () => {
  it('uses the given options', () => {
    expect(formatDate('2026-10-29T09:05:00Z', utc, { month: 'short', day: 'numeric' })).toBe('Oct 29');
  });
});

describe('relativeTime', () => {
  it.each([
    ['2026-10-29T11:59:30Z', { value: -30, unit: 'second' }],
    ['2026-10-29T11:55:00Z', { value: -5, unit: 'minute' }],
    ['2026-10-29T09:00:00Z', { value: -3, unit: 'hour' }],
    ['2026-10-26T12:00:00Z', { value: -3, unit: 'day' }],
  ])('picks the unit for %s', (iso, expected) => {
    expect(relativeTime(iso, now)).toEqual(expected);
  });

  it('formats with Intl', () => {
    expect(formatRelative('2026-10-29T11:55:00Z', now, utc)).toBe('5 minutes ago');
  });
});

describe('durationParts', () => {
  it.each([
    [42, { kind: 'seconds', seconds: 42 }],
    [252, { kind: 'minutes', minutes: 4, seconds: 12 }],
    [7500, { kind: 'hours', hours: 2, minutes: 5 }],
    [273600, { kind: 'days', days: 3, hours: 4 }],
    [-5, { kind: 'seconds', seconds: 0 }],
  ])('splits %i seconds', (seconds, expected) => {
    expect(durationParts(seconds)).toEqual(expected);
  });
});

describe('secondsBetween', () => {
  it('counts whole seconds and never goes below zero', () => {
    expect(secondsBetween('2026-10-29T11:58:30Z', now)).toBe(90);
    expect(secondsBetween('2026-10-29T12:01:00Z', now)).toBe(0);
  });
});
