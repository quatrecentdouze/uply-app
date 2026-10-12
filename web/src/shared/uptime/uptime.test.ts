import { describe, expect, it } from 'vitest';
import { parseUptimeSettings, uptimeSettings, uptimeTone } from '@/shared/uptime/uptime';

const thresholds = { healthy: 99.9, degraded: 99 };

describe('uptime settings', () => {
  it('loads the shipped file shared by the dashboard and the status pages', () => {
    expect(uptimeSettings.thresholds).toEqual(thresholds);
  });

  it('names the file and the key of a wrong value', () => {
    expect(() => parseUptimeSettings({ thresholds: { healthy: 98, degraded: 99 } })).toThrow(/uptimeSettings\.json: thresholds\.degraded/);
    expect(() => parseUptimeSettings({ thresholds: { healthy: 101, degraded: 99 } })).toThrow(/thresholds\.healthy/);
  });
});

describe('uptimeTone', () => {
  it.each([
    [100, 'positive'],
    [99.9, 'positive'],
    [99.5, 'warning'],
    [99, 'warning'],
    [98.2, 'alert'],
    [null, 'neutral'],
  ] as const)('reads %s as %s', (percent, tone) => {
    expect(uptimeTone(percent, thresholds)).toBe(tone);
  });
});
