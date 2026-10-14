import { screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';
import { renderApp, signedIn, withWorkspace } from '@/app/testing/renderApp';
import type { MonitorStats } from '@/shared/api/resources';
import { fakeFetch } from '@/shared/testing/fakeFetch';
import { alertChannelFixture, incidentFixture, listOf, meFixture, monitorFixture, organizationId } from '@/shared/testing/fixtures';

const base = `/api/v1/organizations/${organizationId}`;
const monitor = monitorFixture({ id: 'mon-1', name: 'Public API', state: 'down' });

function stats(uptime: number): MonitorStats {
  return {
    window: '24h',
    uptime_percent: uptime,
    checks_total: 1440,
    response_ms: { avg: 120, min: 80, max: 900 },
    series: [{ start: '2026-10-29T11:00:00Z', uptime_percent: uptime, avg_response_ms: 120 }],
    timings: { dns_ms: 10, connect_ms: 20, tls_ms: 30, ttfb_ms: 60 },
  };
}

function openDetail(role: 'owner' | 'viewer' = 'owner') {
  const me = meFixture({ memberships: [{ id: 'm1', role, organization: { id: organizationId, name: 'Demo', slug: 'demo' }, joined_at: null }] });
  const server = withWorkspace(signedIn(fakeFetch([{ method: 'GET', path: '/sanctum/csrf-cookie', status: 204 }]), me), {
    monitors: [monitor],
    incidents: [incidentFixture({ id: 'inc-1' })],
  });
  server.respond({ method: 'GET', path: `${base}/monitors/mon-1`, body: { data: monitor } });
  server.respond({ method: 'GET', path: `${base}/monitors/mon-1/stats`, body: { data: stats(99.5) } });
  server.respond({
    method: 'GET',
    path: `${base}/monitors/mon-1/checks`,
    body: {
      data: [{ checked_at: '2026-10-29T12:00:00Z', outcome: 'down', failure_kind: 'timeout', http_status: null, response_ms: null, dns_ms: null, connect_ms: null, tls_ms: null, ttfb_ms: null, error_message: null }],
      links: { first: null, last: null, prev: null, next: null },
      meta: { path: null, per_page: 10, next_cursor: 'older', prev_cursor: null },
    },
  });
  server.respond({ method: 'GET', path: `${base}/monitors/mon-1/alert-channels`, body: { data: [alertChannelFixture({ name: 'On-call email' })] } });
  server.respond({ method: 'GET', path: `${base}/alert-channels`, body: listOf([alertChannelFixture({ name: 'On-call email' })]) });
  return renderApp({ path: '/monitors/mon-1', server });
}

describe('monitor detail page', () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  it('assembles the state, the uptimes, the timings, the checks, the incidents and the channels', async () => {
    openDetail();

    expect(await screen.findByRole('heading', { name: 'Public API' })).toBeInTheDocument();
    expect(await screen.findByRole('heading', { name: 'Uptime over 7 days' })).toBeInTheDocument();
    expect(await screen.findAllByText('99.5%')).toHaveLength(3);
    expect(screen.getByText('First byte')).toBeInTheDocument();
    expect(await within(await screen.findByRole('table', { name: 'Recent checks of the monitor' })).findByText('Timeout')).toBeInTheDocument();
    expect(await screen.findByRole('link', { name: /HTTP 503/ })).toHaveAttribute('href', '/incidents/inc-1');
    expect(await within(screen.getByRole('region', { name: 'Alert channels' })).findByText('On-call email')).toBeInTheDocument();
  });

  it('loads older checks with the cursor', async () => {
    const app = openDetail();

    await app.user.click(await screen.findByRole('button', { name: 'Load older checks' }));

    await waitFor(() => {
      expect(app.server.requestsTo('GET', `${base}/monitors/mon-1/checks`).some((request) => request.search.includes('cursor=older'))).toBe(true);
    });
  });

  it('pauses the monitor with its switch', async () => {
    const app = openDetail();
    app.server.respond({ method: 'PATCH', path: `${base}/monitors/mon-1`, body: { data: { ...monitor, is_paused: true } } });

    const toggle = await screen.findByRole('switch', { name: 'Paused' });
    app.server.respond({ method: 'GET', path: `${base}/monitors/mon-1`, body: { data: { ...monitor, is_paused: true } } });
    await app.user.click(toggle);

    await waitFor(() => {
      expect(app.server.requestsTo('PATCH', `${base}/monitors/mon-1`)[0]?.body).toEqual({ is_paused: true });
    });
    expect(await screen.findByRole('switch', { name: 'Paused' })).toBeChecked();
  });

  it('links the channels chosen in the form', async () => {
    const app = openDetail();
    const channel = alertChannelFixture({ id: 'ch-slack', type: 'slack', name: 'Ops Slack', recipients: null, webhook_hint: { host: 'hooks.slack.com', last_four: 'abcd' } });
    app.server.respond({ method: 'GET', path: `${base}/alert-channels`, body: listOf([alertChannelFixture(), channel]) });
    app.server.respond({ method: 'PUT', path: `${base}/monitors/mon-1/alert-channels`, body: { data: [alertChannelFixture(), channel] } });

    await app.user.click(await screen.findByRole('button', { name: 'Edit' }));
    const dialog = within(await screen.findByRole('dialog', { name: 'Edit Public API' }));
    await app.user.click(await dialog.findByRole('checkbox', { name: /Ops Slack/ }));
    await app.user.click(dialog.getByRole('button', { name: 'Save changes' }));

    await waitFor(() => {
      expect(app.server.requestsTo('PUT', `${base}/monitors/mon-1/alert-channels`)[0]?.body).toEqual({ channel_ids: [alertChannelFixture().id, 'ch-slack'] });
    });
    expect(app.server.requestsTo('PATCH', `${base}/monitors/mon-1`)).toEqual([]);
  });

  it('deletes the monitor after the confirmation and goes back to the list', async () => {
    const app = openDetail();
    app.server.respond({ method: 'DELETE', path: `${base}/monitors/mon-1`, status: 204 });

    await app.user.click(await screen.findByRole('button', { name: 'Delete' }));
    await app.user.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Delete monitor' }));

    await waitFor(() => {
      expect(app.router.state.location.pathname).toBe('/monitors');
    });
  });

  it('shows the not found page for an unknown monitor', async () => {
    const app = openDetail();
    app.server.respond({ method: 'GET', path: `${base}/monitors/mon-1`, status: 404, body: { message: 'Not found.' } });
    await app.router.invalidate();

    expect(await screen.findByRole('heading', { name: 'Page not found' })).toBeInTheDocument();
  });

  it('hides the pause, edit and delete controls from a viewer', async () => {
    openDetail('viewer');

    expect(await screen.findByRole('heading', { name: 'Public API' })).toBeInTheDocument();
    expect(screen.queryByRole('switch', { name: 'Paused' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Edit' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Delete' })).toBeNull();
  });

  it('offers a retry on the cards whose data could not load', async () => {
    const app = openDetail();
    app.server.respond({ method: 'GET', path: `${base}/monitors/mon-1/stats`, status: 500, body: { message: 'Server Error' } });
    app.server.respond({ method: 'GET', path: `${base}/monitors/mon-1/alert-channels`, status: 500, body: { message: 'Server Error' } });
    await app.queryClient.resetQueries({ queryKey: ['organizations', organizationId, 'monitors', 'stats'] });
    await app.queryClient.resetQueries({ queryKey: ['organizations', organizationId, 'alert-channels'] });

    const linked = within(await screen.findByRole('region', { name: 'Alert channels' }));
    expect(await linked.findByRole('button', { name: 'Retry' })).toBeInTheDocument();
    expect(await within(screen.getByRole('region', { name: 'Uptime over 24 h' })).findByRole('button', { name: 'Retry' })).toBeInTheDocument();

    app.server.respond({ method: 'GET', path: `${base}/monitors/mon-1/alert-channels`, body: { data: [alertChannelFixture({ name: 'On-call email' })] } });
    await app.user.click(linked.getByRole('button', { name: 'Retry' }));

    expect(await linked.findByText('On-call email')).toBeInTheDocument();
  });
});
