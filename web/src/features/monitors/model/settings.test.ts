import { describe, expect, it } from 'vitest';
import shipped from '@/features/monitors/model/dashboardSettings.json';
import { dashboardSettings, parseDashboardSettings } from '@/features/monitors/model/settings';

describe('dashboard settings', () => {
  it('loads the shipped file', () => {
    expect(dashboardSettings.uptime_thresholds).toEqual({ healthy: 99.9, degraded: 99 });
    expect(dashboardSettings.default_window).toBe('24h');
  });

  it('names the file and the key of a wrong value', () => {
    expect(() => parseDashboardSettings({ ...shipped, panel_monitors: 0 })).toThrow(/dashboardSettings\.json: panel_monitors/);
    expect(() => parseDashboardSettings({ ...shipped, uptime_thresholds: { healthy: 98, degraded: 99 } })).toThrow(/uptime_thresholds\.degraded/);
  });
});
