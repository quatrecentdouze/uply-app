import { describe, expect, it } from 'vitest';
import {
  averageResponse,
  bucketLabelFormat,
  displayState,
  isWindow,
  panelMonitors,
  responsePoints,
  responseTone,
  stateBreakdown,
  uptimeFloor,
  uptimePoints,
  uptimeTone,
} from '@/features/monitors/model/dashboard';
import { monitorFixture, overviewFixture } from '@/shared/testing/fixtures';

const thresholds = { healthy: 99.9, degraded: 99 };

describe('uptimeTone', () => {
  it.each([
    [100, 'positive'],
    [99.9, 'positive'],
    [99.5, 'warning'],
    [98.2, 'alert'],
    [null, 'neutral'],
  ] as const)('reads %s as %s', (percent, tone) => {
    expect(uptimeTone(percent, thresholds)).toBe(tone);
  });
});

describe('responseTone', () => {
  it('flags a slow answer', () => {
    expect(responseTone(1200, 1000)).toBe('warning');
    expect(responseTone(80, 1000)).toBe('positive');
    expect(responseTone(null, 1000)).toBe('neutral');
  });
});

describe('displayState', () => {
  it('shows a paused monitor as paused whatever its last state', () => {
    expect(displayState({ state: 'down', is_paused: true })).toBe('paused');
    expect(displayState({ state: 'down', is_paused: false })).toBe('down');
  });
});

describe('stateBreakdown', () => {
  it('counts every monitor and gives the share of each state', () => {
    const breakdown = stateBreakdown({ up: 2, down: 1, paused: 1, pending: 0 });

    expect(breakdown.total).toBe(4);
    expect(breakdown.segments).toEqual([
      { state: 'up', count: 2, percent: 50 },
      { state: 'down', count: 1, percent: 25 },
      { state: 'paused', count: 1, percent: 25 },
    ]);
  });

  it('adds pending only when a monitor waits for its first result', () => {
    expect(stateBreakdown({ up: 0, down: 0, paused: 0, pending: 1 }).segments.map((segment) => segment.state)).toEqual(['up', 'down', 'paused', 'pending']);
  });

  it('keeps zero shares without monitors', () => {
    expect(stateBreakdown({ up: 0, down: 0, paused: 0, pending: 0 })).toEqual({
      total: 0,
      segments: [
        { state: 'up', count: 0, percent: 0 },
        { state: 'down', count: 0, percent: 0 },
        { state: 'paused', count: 0, percent: 0 },
      ],
    });
  });
});

describe('panelMonitors', () => {
  it('shows the monitors that need attention first, then by name, up to the size', () => {
    const monitors = [
      monitorFixture({ id: 'a', name: 'Zeta', state: 'up' }),
      monitorFixture({ id: 'b', name: 'Alpha', state: 'up', is_paused: true }),
      monitorFixture({ id: 'c', name: 'Beta', state: 'down' }),
      monitorFixture({ id: 'd', name: 'Gamma', state: 'pending' }),
      monitorFixture({ id: 'e', name: 'Delta', state: 'up' }),
    ];

    expect(panelMonitors(monitors, 4).map((monitor) => monitor.id)).toEqual(['c', 'd', 'e', 'a']);
  });
});

describe('series', () => {
  const series = overviewFixture().series;

  it('keeps the gaps of buckets without checks', () => {
    expect(uptimePoints(series).map((point) => point.value)).toEqual([100, 95, null]);
    expect(responsePoints(series).map((point) => point.value)).toEqual([110, 180, null]);
  });

  it('averages the buckets that have an answer', () => {
    expect(averageResponse(series)).toBe(145);
    expect(averageResponse([])).toBeNull();
  });

  it('starts the uptime axis a little under the lowest value', () => {
    expect(uptimeFloor(uptimePoints(series))).toBe(90);
    expect(uptimeFloor([])).toBe(95);
  });
});

describe('windows', () => {
  it('formats the axis by hour for a day and by date beyond', () => {
    expect(bucketLabelFormat('24h')).toEqual({ hour: '2-digit', minute: '2-digit' });
    expect(bucketLabelFormat('30d')).toEqual({ month: 'short', day: 'numeric' });
  });

  it('recognizes a window id', () => {
    expect(isWindow('7d')).toBe(true);
    expect(isWindow('1y')).toBe(false);
  });
});
