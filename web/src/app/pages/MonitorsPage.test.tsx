import { screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';
import { renderApp, signedIn, withWorkspace } from '@/app/testing/renderApp';
import { fakeFetch } from '@/shared/testing/fakeFetch';
import { listOf, meFixture, monitorFixture, organizationId } from '@/shared/testing/fixtures';

const base = `/api/v1/organizations/${organizationId}`;
const monitors = [
  monitorFixture({ id: 'mon-api', name: 'Public API', uptime_24h: 99.95 }),
  monitorFixture({ id: 'mon-billing', name: 'Billing API', state: 'down', uptime_24h: 42.5, last_response_ms: null }),
];

function openMonitors(role: 'owner' | 'viewer' = 'owner') {
  const me = meFixture({ memberships: [{ id: 'm1', role, organization: { id: organizationId, name: 'Demo', slug: 'demo' }, joined_at: null }] });
  const server = withWorkspace(signedIn(fakeFetch([{ method: 'GET', path: '/sanctum/csrf-cookie', status: 204 }]), me), { monitors });
  server.respond({ method: 'GET', path: `${base}/alert-channels`, body: listOf([]) });
  return renderApp({ path: '/monitors', server });
}

async function table() {
  return within(await screen.findByRole('table', { name: 'Monitors of the organization' }));
}

describe('monitors page', () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  it('lists the monitors with their state and uptime', async () => {
    openMonitors();
    const rows = await table();

    expect(rows.getByRole('link', { name: /Public API/ })).toHaveAttribute('href', '/monitors/mon-api');
    expect(rows.getByText('99.95%')).toBeInTheDocument();
    expect(rows.getByText('Down')).toBeInTheDocument();
  });

  it('filters by state and searches by name', async () => {
    const app = openMonitors();
    await table();

    await app.user.click(screen.getByRole('button', { name: 'Down' }));
    await waitFor(() => {
      expect(app.server.requestsTo('GET', `${base}/monitors`).some((request) => request.search.includes('state=down'))).toBe(true);
    });
    await app.user.type(screen.getByRole('searchbox', { name: 'Search by name or URL' }), 'billing');

    await waitFor(() => {
      expect(app.server.requestsTo('GET', `${base}/monitors`).some((request) => request.search.includes('search=billing'))).toBe(true);
    });
  });

  it('pauses a monitor from its menu', async () => {
    const app = openMonitors();
    app.server.respond({ method: 'PATCH', path: `${base}/monitors/mon-api`, body: { data: { ...monitors[0], is_paused: true } } });

    await app.user.click((await table()).getByRole('button', { name: 'Actions for Public API' }));
    await app.user.click(await screen.findByRole('menuitem', { name: 'Pause' }));

    await waitFor(() => {
      expect(app.server.requestsTo('PATCH', `${base}/monitors/mon-api`)[0]?.body).toEqual({ is_paused: true });
    });
  });

  it('deletes a monitor only after the confirmation', async () => {
    const app = openMonitors();
    app.server.respond({ method: 'DELETE', path: `${base}/monitors/mon-billing`, status: 204 });

    await app.user.click((await table()).getByRole('button', { name: 'Actions for Billing API' }));
    await app.user.click(await screen.findByRole('menuitem', { name: 'Delete' }));
    const dialog = within(await screen.findByRole('alertdialog', { name: 'Delete Billing API?' }));
    expect(app.server.requestsTo('DELETE', `${base}/monitors/mon-billing`)).toEqual([]);
    await app.user.click(dialog.getByRole('button', { name: 'Delete monitor' }));

    await waitFor(() => {
      expect(app.server.requestsTo('DELETE', `${base}/monitors/mon-billing`)).toHaveLength(1);
    });
    await waitFor(() => {
      expect(screen.queryByRole('alertdialog')).toBeNull();
    });
  });

  it('adds a monitor and opens its detail', async () => {
    const app = openMonitors();
    app.server.respond({ method: 'POST', path: `${base}/monitors`, status: 201, body: { data: monitorFixture({ id: 'mon-new', name: 'New API' }) } });

    await table();
    await app.user.click(screen.getByRole('button', { name: 'Add monitor' }));
    const dialog = within(await screen.findByRole('dialog', { name: 'Add a monitor' }));
    await app.user.type(dialog.getByLabelText('Name'), 'New API');
    await app.user.type(dialog.getByLabelText('URL'), 'https://new.example.test');
    await app.user.click(dialog.getByRole('button', { name: 'Add monitor' }));

    await waitFor(() => {
      expect(app.router.state.location.pathname).toBe('/monitors/mon-new');
    });
  });

  it('shows no write action to a viewer', async () => {
    openMonitors('viewer');
    const rows = await table();

    expect(rows.queryByRole('button', { name: /Actions for/ })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Add monitor' })).toBeNull();
  });
});
