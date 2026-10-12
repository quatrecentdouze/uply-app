import type { Window } from '@/shared/api/queryKeys';
import type { Monitor, Overview, SeriesPoint } from '@/shared/api/resources';
import type { TextKey } from '@/shared/i18n/textKey';
import type { Tone } from '@/shared/uptime/uptime';

export type DisplayState = 'up' | 'down' | 'pending' | 'paused';

export const windows: readonly Window[] = ['24h', '7d', '30d'];

export const windowTextKeys: Record<Window, TextKey<'monitors'>> = {
  '24h': 'monitors:windows.24h',
  '7d': 'monitors:windows.7d',
  '30d': 'monitors:windows.30d',
};

export const windowTabKeys: Record<Window, TextKey<'monitors'>> = {
  '24h': 'monitors:window_tabs.24h',
  '7d': 'monitors:window_tabs.7d',
  '30d': 'monitors:window_tabs.30d',
};

export const stateTextKeys: Record<DisplayState, TextKey<'monitors'>> = {
  up: 'monitors:states.up',
  down: 'monitors:states.down',
  pending: 'monitors:states.pending',
  paused: 'monitors:states.paused',
};

export const toneTextKeys: Record<Tone, TextKey<'monitors'>> = {
  positive: 'monitors:tone.positive',
  warning: 'monitors:tone.warning',
  alert: 'monitors:tone.alert',
  neutral: 'monitors:tone.neutral',
};

export function isWindow(value: string): value is Window {
  return windows.some((window) => window === value);
}

export function displayState(monitor: Pick<Monitor, 'state' | 'is_paused'>): DisplayState {
  return monitor.is_paused ? 'paused' : monitor.state;
}

export function responseTone(milliseconds: number | null, slowMilliseconds: number): Tone {
  if (milliseconds === null) {
    return 'neutral';
  }
  return milliseconds > slowMilliseconds ? 'warning' : 'positive';
}

export interface StateSegment {
  state: DisplayState;
  count: number;
  percent: number;
}

export interface StateBreakdown {
  total: number;
  segments: StateSegment[];
}

const breakdownOrder: readonly DisplayState[] = ['up', 'down', 'paused', 'pending'];
const hundred = 100;

export function stateBreakdown(counts: Overview['counts']): StateBreakdown {
  const total = counts.up + counts.down + counts.paused + counts.pending;
  const segments = breakdownOrder
    .filter((state) => state !== 'pending' || counts.pending > 0)
    .map((state) => ({ state, count: counts[state], percent: total === 0 ? 0 : (counts[state] / total) * hundred }));
  return { total, segments };
}

const panelOrder: Record<DisplayState, number> = { down: 0, pending: 1, up: 2, paused: 3 };

export function panelMonitors(monitors: readonly Monitor[], size: number): Monitor[] {
  return [...monitors]
    .sort((left, right) => panelOrder[displayState(left)] - panelOrder[displayState(right)] || left.name.localeCompare(right.name))
    .slice(0, size);
}

export interface ChartPoint {
  start: string;
  value: number | null;
}

export function uptimePoints(series: readonly SeriesPoint[]): ChartPoint[] {
  return series.flatMap((point) => (point.start === null ? [] : [{ start: point.start, value: point.uptime_percent }]));
}

export function responsePoints(series: readonly SeriesPoint[]): ChartPoint[] {
  return series.flatMap((point) => (point.start === null ? [] : [{ start: point.start, value: point.avg_response_ms }]));
}

export function averageResponse(series: readonly SeriesPoint[]): number | null {
  const values = series.flatMap((point) => (point.avg_response_ms === null ? [] : [point.avg_response_ms]));
  return values.length === 0 ? null : Math.round(values.reduce((sum, value) => sum + value, 0) / values.length);
}

export function uptimeFloor(points: readonly ChartPoint[]): number {
  const values = points.flatMap((point) => (point.value === null ? [] : [point.value]));
  const lowest = values.length === 0 ? hundred : Math.min(...values);
  const step = 5;
  return Math.max(0, Math.floor((lowest - 1) / step) * step);
}

export function bucketLabelFormat(window: Window): Intl.DateTimeFormatOptions {
  return window === '24h' ? { hour: '2-digit', minute: '2-digit' } : { month: 'short', day: 'numeric' };
}

export function bucketTooltipFormat(window: Window): Intl.DateTimeFormatOptions {
  return window === '24h' ? { hour: '2-digit', minute: '2-digit' } : { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
}
