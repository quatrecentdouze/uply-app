import { describe, expect, it } from 'vitest';
import { barViews, incidentTiming, overallStateOf, overallTones, publicMonitorState, shownBarCount } from '@/features/status-pages/model/publicPage';
import { uptimeSettings } from '@/shared/uptime/uptime';

const thresholds = uptimeSettings.thresholds;

function bars(values: readonly (number | null)[]) {
  return values.map((uptime_percent, index) => ({ date: `2026-10-${String(index + 1).padStart(2, '0')}`, uptime_percent, maintenance: false }));
}

describe('barViews', () => {
  it('colors a bar by its uptime with the dashboard thresholds, a day without check is neutral', () => {
    const views = barViews(bars([100, 99.9, 99.5, 99, 98.9, null]), thresholds, 30);

    expect(views.map((view) => view.tone)).toEqual(['positive', 'positive', 'warning', 'warning', 'alert', 'neutral']);
  });

  it('keeps the bars from oldest to newest and hides all but the last ones on a narrow screen', () => {
    const views = barViews(bars(Array.from({ length: 90 }, () => 100)), thresholds, 30);

    expect(views).toHaveLength(90);
    expect(views.filter((view) => !view.compactHidden).map((view) => view.index)).toEqual(Array.from({ length: 30 }, (_, index) => index + 60));
    expect(views[0]?.date).toBe('2026-10-01');
  });

  it('hides nothing when there are fewer bars than the narrow count', () => {
    expect(barViews(bars([100, 100]), thresholds, 30).some((view) => view.compactHidden)).toBe(false);
  });
});

describe('shownBarCount', () => {
  it('counts 90 bars on a wide screen and 30 under the narrow breakpoint', () => {
    expect(shownBarCount(90, 30, false)).toBe(90);
    expect(shownBarCount(90, 30, true)).toBe(30);
    expect(shownBarCount(12, 30, true)).toBe(12);
  });
});

describe('states', () => {
  it('reads the overall state and its tone', () => {
    expect(overallStateOf('partial_outage')).toBe('partial_outage');
    expect(overallStateOf('unknown')).toBeNull();
    expect(overallTones.major_outage).toBe('alert');
    expect(overallTones.operational).toBe('positive');
  });

  it('reads a monitor state, an unknown one as pending', () => {
    expect(publicMonitorState('paused')).toBe('paused');
    expect(publicMonitorState('maintenance')).toBe('maintenance');
    expect(publicMonitorState('weird')).toBe('pending');
  });
});

describe('incidentTiming', () => {
  it('gives the duration of a resolved incident and marks an open one ongoing', () => {
    expect(incidentTiming({ resolved_at: '2026-10-28T10:05:00Z', duration_seconds: 300 })).toEqual({ kind: 'resolved', seconds: 300 });
    expect(incidentTiming({ resolved_at: null, duration_seconds: null })).toEqual({ kind: 'ongoing' });
  });
});
