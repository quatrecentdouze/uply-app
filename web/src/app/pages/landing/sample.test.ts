import { describe, expect, it } from 'vitest';
import shipped from '@/app/pages/landing/sample.json';
import { landingSample, parseLandingSample } from '@/app/pages/landing/sample';

describe('landing sample', () => {
  it('loads the shipped sample with hosts under example.com only', () => {
    expect(landingSample.monitors.length).toBeGreaterThan(0);
    expect(landingSample.monitors.every((monitor) => new URL(monitor.url).hostname.endsWith('example.com'))).toBe(true);
    expect(JSON.stringify(shipped)).not.toMatch(/@|uply\.test|webhook|localhost|127\.0\.0\.1/);
  });

  it('counts the monitor states the way the overview does, paused apart', () => {
    expect(landingSample.overview.counts).toEqual({ up: 3, down: 1, pending: 0, paused: 1 });
  });

  it('dates the 90 bars of each monitor from oldest to the sample day', () => {
    const bars = landingSample.statusPage.monitors[0]?.bars ?? [];

    expect(bars).toHaveLength(90);
    expect(bars[0]?.date).toBe('2026-08-01');
    expect(bars.at(-1)?.date).toBe('2026-10-29');
  });

  it('names the monitor of each incident and measures the resolved ones', () => {
    const [open, resolved] = landingSample.incidents;

    expect(open?.monitor.name).toBe('Checkout');
    expect(open?.duration_seconds).toBeNull();
    expect(resolved?.duration_seconds).toBe(252);
  });

  it('names the file and the key of a wrong value', () => {
    const broken = { ...shipped, monitors: [{ ...shipped.monitors[0], url: 'https://api.acme.test/health' }] };

    expect(() => parseLandingSample(broken)).toThrow(/sample\.json: monitors\.0\.url/);
  });
});
