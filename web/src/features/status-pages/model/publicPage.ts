import type { PublicBar, PublicIncident } from '@/shared/api/resources';
import type { TextKey } from '@/shared/i18n/textKey';
import { type Tone, type UptimeThresholds, uptimeTone } from '@/shared/uptime/uptime';

export type OverallState = 'operational' | 'partial_outage' | 'major_outage';

const overallStates: readonly OverallState[] = ['operational', 'partial_outage', 'major_outage'];

export function overallStateOf(value: string): OverallState | null {
  return overallStates.find((state) => state === value) ?? null;
}

export const overallTones: Record<OverallState, Tone> = {
  operational: 'positive',
  partial_outage: 'warning',
  major_outage: 'alert',
};

export const overallTextKeys: Record<OverallState, TextKey<'status-pages'>> = {
  operational: 'status-pages:public.overall.operational',
  partial_outage: 'status-pages:public.overall.partial_outage',
  major_outage: 'status-pages:public.overall.major_outage',
};

export type PublicMonitorState = 'up' | 'down' | 'pending' | 'paused' | 'maintenance';

const monitorStates: readonly PublicMonitorState[] = ['up', 'down', 'pending', 'paused', 'maintenance'];

export function publicMonitorState(value: string): PublicMonitorState {
  return monitorStates.find((state) => state === value) ?? 'pending';
}

export interface BarView {
  date: string;
  percent: number | null;
  tone: Tone;
  index: number;
  compactHidden: boolean;
}

export function barViews(bars: readonly PublicBar[], thresholds: UptimeThresholds, compactCount: number): BarView[] {
  const firstCompact = Math.max(0, bars.length - compactCount);
  return bars.map((bar, index) => ({
    date: bar.date,
    percent: bar.uptime_percent,
    tone: uptimeTone(bar.uptime_percent, thresholds),
    index,
    compactHidden: index < firstCompact,
  }));
}

export function shownBarCount(total: number, compactCount: number, compact: boolean): number {
  return compact ? Math.min(total, compactCount) : total;
}

export type IncidentTiming = { kind: 'ongoing' } | { kind: 'resolved'; seconds: number };

export function incidentTiming(incident: Pick<PublicIncident, 'resolved_at' | 'duration_seconds'>): IncidentTiming {
  return incident.resolved_at === null || incident.duration_seconds === null ? { kind: 'ongoing' } : { kind: 'resolved', seconds: incident.duration_seconds };
}

export function barDateIso(date: string): string {
  return `${date}T00:00:00Z`;
}
