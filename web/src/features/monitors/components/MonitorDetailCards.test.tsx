import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { TimingBreakdownCard } from '@/features/monitors/components/MonitorDetailCards';
import type { MonitorStats } from '@/shared/api/resources';
import { renderPage } from '@/shared/testing/renderPage';

function stats(timings: MonitorStats['timings']): MonitorStats {
  return { window: '24h', uptime_percent: 100, checks_total: 3, response_ms: { avg: 1, min: 1, max: 2 }, series: [], timings };
}

describe('TimingBreakdownCard', () => {
  it('shows measured steps even when their averages round to zero', async () => {
    renderPage(() => <TimingBreakdownCard stats={stats({ dns_ms: null, connect_ms: 0, tls_ms: null, ttfb_ms: 0 })} />);

    expect(await screen.findByText('Connect')).toBeInTheDocument();
    expect(screen.getAllByText('0 ms')).toHaveLength(2);
    expect(screen.queryByText('No data yet')).toBeNull();
  });

  it('says no data without any measured step', async () => {
    renderPage(() => <TimingBreakdownCard stats={stats({ dns_ms: null, connect_ms: null, tls_ms: null, ttfb_ms: null })} />);

    expect(await screen.findByText('No data yet')).toBeInTheDocument();
  });
});
