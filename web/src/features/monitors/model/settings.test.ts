import { describe, expect, it } from 'vitest';
import shipped from '@/features/monitors/model/dashboardSettings.json';
import { dashboardSettings, parseDashboardSettings } from '@/features/monitors/model/settings';

describe('dashboard settings', () => {
  it('loads the shipped file', () => {
    expect(dashboardSettings.default_window).toBe('24h');
    expect(dashboardSettings.slow_response_ms).toBe(1000);
  });

  it('names the file and the key of a wrong value', () => {
    expect(() => parseDashboardSettings({ ...shipped, panel_monitors: 0 })).toThrow(/dashboardSettings\.json: panel_monitors/);
    expect(() => parseDashboardSettings({ ...shipped, default_window: '1y' })).toThrow(/default_window/);
  });
});
