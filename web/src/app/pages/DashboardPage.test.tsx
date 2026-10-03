import { screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { renderApp, signedIn, withWorkspace } from '@/app/testing/renderApp';
import { fakeFetch } from '@/shared/testing/fakeFetch';
import { incidentFixture, monitorFixture, organizationId, overviewFixture } from '@/shared/testing/fixtures';

const monitors = [
  monitorFixture({ id: 'mon-up', name: 'Public API', state: 'up', last_response_ms: 120 }),
  monitorFixture({ id: 'mon-down', name: 'Billing API', state: 'down', last_response_ms: null }),
  monitorFixture({ id: 'mon-paused', name: 'Search', state: 'up', is_paused: true, last_response_ms: null }),
];

function openDashboard(data: Parameters<typeof withWorkspace>[1] = {}) {
  const server = withWorkspace(signedIn(fakeFetch()), {
    monitors,
    incidents: [
      incidentFixture({ id: 'inc-open', monitor: { id: 'mon-down', name: 'Billing API' }, started_at: '2026-10-29T10:00:00Z' }),
      incidentFixture({
        id: 'inc-done',
        monitor: { id: 'mon-up', name: null },
        resolved_at: '2026-10-29T09:04:12Z',
        started_at: '2026-10-29T09:00:00Z',
        duration_seconds: 252,
        cause: { failure_kind: 'timeout', http_status: null, message: 'x' },
      }),
    ],
    openIncidents: 1,
    ...data,
  });
  return renderApp({ path: '/', server });
}

async function card(name: string) {
  return within(await screen.findByRole('region', { name }));
}

describe('DashboardPage', () => {
  it('shows the uptime of the period with its tone', async () => {
    openDashboard();
    const uptime = await card('Uptime');

    expect(await uptime.findByText('98.75%')).toBeInTheDocument();
    expect(uptime.getByText('Failing')).toBeInTheDocument();
    expect(uptime.getByRole('img', { name: 'Uptime of each part of the period' })).toBeInTheDocument();
  });

  it('breaks the monitors down by state', async () => {
    openDashboard();
    const states = await card('Monitor states');

    expect(await states.findByText('5 monitors')).toBeInTheDocument();
    const legend = within(states.getByRole('list', { name: 'State breakdown' }));
    expect(legend.getAllByRole('listitem').map((item) => item.textContent)).toEqual(['Up3 monitors', 'Down1 monitor', 'Paused1 monitor']);
  });

  it('averages the response time and switches its period with the tabs', async () => {
    const app = openDashboard();
    const response = await card('Response time');

    expect(await response.findByText('145 ms')).toBeInTheDocument();
    await app.user.click(response.getByRole('tab', { name: '7 days' }));

    await waitFor(() => {
      expect(app.server.requestsTo('GET', `/api/v1/organizations/${organizationId}/overview`).map((request) => request.search)).toContain('?window=7d');
    });
    expect(response.getByRole('tab', { name: '7 days' })).toHaveAttribute('aria-selected', 'true');
  });

  it('changes the uptime period from its menu', async () => {
    const app = openDashboard();
    const uptime = await card('Uptime');

    await app.user.click(uptime.getByRole('button', { name: 'Choose the period' }));
    await app.user.click(await screen.findByRole('menuitemradio', { name: 'Last 30 days' }));

    await waitFor(() => {
      expect(app.server.requestsTo('GET', `/api/v1/organizations/${organizationId}/overview`).map((request) => request.search)).toContain('?window=30d');
    });
  });

  it('lists the recent incidents with their cause, duration and status', async () => {
    openDashboard();
    const incidents = await card('Recent incidents');

    const rows = within(await incidents.findByRole('table')).getAllByRole('row');
    expect(rows).toHaveLength(3);
    expect(within(rows[1] ?? document.body).getByText('Ongoing')).toBeInTheDocument();
    expect(within(rows[1] ?? document.body).getByText('HTTP 503')).toBeInTheDocument();
    expect(within(rows[2] ?? document.body).getByText('Deleted monitor')).toBeInTheDocument();
    expect(within(rows[2] ?? document.body).getByText('4 min 12 s')).toBeInTheDocument();
    expect(within(rows[2] ?? document.body).getByText('Timeout')).toBeInTheDocument();
  });

  it('puts the monitors that need attention first in the panel', async () => {
    openDashboard();
    const panel = await card('Monitors');

    const links = await panel.findAllByRole('link', { name: /^Open / });
    expect(links.map((link) => link.getAttribute('aria-label'))).toEqual(['Open Billing API', 'Open Public API', 'Open Search']);
    expect(panel.getByText('120 ms')).toBeInTheDocument();
    expect(panel.getByText('Down')).toBeInTheDocument();
    expect(panel.getByText('Paused')).toBeInTheDocument();
  });

  it('says when there is nothing to show yet', async () => {
    openDashboard({
      monitors: [],
      incidents: [],
      openIncidents: 0,
      overview: overviewFixture({ counts: { up: 0, down: 0, pending: 0, paused: 0 }, uptime_percent: null, series: [] }),
    });

    expect(await screen.findByText('No monitor yet. Add one to start watching a service.')).toBeInTheDocument();
    expect(screen.getByText('No incident so far. Everything is running.')).toBeInTheDocument();
    expect((await card('Uptime')).getByText('No data yet')).toBeInTheDocument();
  });
});
